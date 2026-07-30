<?php

namespace modules\standardsite\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\App;
use craft\helpers\UrlHelper;

class BlueskySyndicationService
{
    public const COLLECTION = BlueskyPostBuilder::COLLECTION;

    /**
     * Sections whose entries get syndicated. Deliberately narrower than
     * StandardSiteService::SUPPORTED_SECTIONS — books and walks aren't blog posts.
     */
    public const SUPPORTED_SECTIONS = ['posts', 'stream'];

    /**
     * app.bsky.embed.external caps the thumbnail blob at 1,000,000 bytes.
     */
    private const MAX_THUMB_BYTES = 1000000;

    private AtProtoClient $client;
    private bool $authenticated = false;

    public function __construct(?AtProtoClient $client = null)
    {
        $this->client = $client ?? new AtProtoClient();
    }

    /**
     * Whether this entry is a candidate for syndication. Says nothing about whether
     * it has already been syndicated — see hasBeenSyndicated() for that.
     */
    public function isEligible(Entry $entry): bool
    {
        if ($entry->getIsDraft() || $entry->getIsRevision() || $entry->isProvisionalDraft) {
            return false;
        }

        if (!$entry->section || !in_array($entry->section->handle, self::SUPPORTED_SECTIONS)) {
            return false;
        }

        if (!$entry->enabled || !$entry->getEnabledForSite()) {
            return false;
        }

        if (!$entry->postDate || !$entry->getUrl()) {
            return false;
        }

        if (!Craft::$app->projectConfig->get('standardsite.publicationUri')) {
            return false;
        }

        return (bool) App::env('BLUESKY_APP_PASSWORD');
    }

    /**
     * The record key this entry's Bluesky post lives at. Derived from the entry rather
     * than stored, the same way site.standard.document records are keyed, which makes
     * writes idempotent without any local bookkeeping.
     */
    public function rKeyForEntry(Entry $entry): string
    {
        return StandardSiteService::tidForEntry($entry);
    }

    public function hasBeenSyndicated(Entry $entry): bool
    {
        $this->authenticate();

        return $this->client->getRecord(self::COLLECTION, $this->rKeyForEntry($entry)) !== null;
    }

    /**
     * Creates the app.bsky.feed.post record for this entry, unless one already exists.
     *
     * @return string|null The record URI, or null if the entry was already syndicated
     */
    public function syndicate(Entry $entry): ?string
    {
        if ($this->hasBeenSyndicated($entry)) {
            return null;
        }

        $record = $this->buildRecord($entry, $this->thumbnailBlob($entry));

        $result = $this->client->putRecord(
            self::COLLECTION,
            $this->rKeyForEntry($entry),
            $record
        );

        return (string) $result['uri'];
    }

    /**
     * @param array<string, mixed>|null $thumbBlob
     * @return array<string, mixed>
     */
    public function buildRecord(Entry $entry, ?array $thumbBlob = null): array
    {
        $url = (string) $entry->getUrl();
        $title = (string) ($entry->title ?? '');
        $summary = $entry->summary ?? null;

        return BlueskyPostBuilder::buildRecord(
            BlueskyPostBuilder::buildText($title, $url),
            $url,
            $title,
            $summary === null ? null : (string) $summary,
            $thumbBlob
        );
    }

    /**
     * Fetches the entry's OG image and uploads it as a blob for the link card.
     *
     * A card without a picture is still a good card, so every failure here degrades
     * to null rather than costing us the post.
     *
     * @return array<string, mixed>|null
     */
    public function thumbnailBlob(Entry $entry): ?array
    {
        $bytes = $this->fetchThumbnail($entry);

        if ($bytes === null) {
            return null;
        }

        try {
            $this->authenticate();

            return $this->client->uploadBlob($bytes, 'image/jpeg');
        } catch (\Throwable $e) {
            Craft::error(
                "Failed to upload Bluesky thumbnail for entry {$entry->id}: {$e->getMessage()}",
                'standardsite'
            );

            return null;
        }
    }

    /**
     * Retrieves the entry's OG image from the site's own image endpoint, shrinking it
     * if it's over the blob size limit.
     */
    public function fetchThumbnail(Entry $entry): ?string
    {
        if ($entry->slug === null) {
            return null;
        }

        $url = UrlHelper::siteUrl(
            'api/post-images/v2/' . $entry->slug,
            null,
            null,
            $entry->siteId
        );

        try {
            // A cold cache renders the image with headless Chromium, so allow plenty of time.
            $response = Craft::createGuzzleClient(['timeout' => 60])->get($url);
            $bytes = (string) $response->getBody();
        } catch (\Throwable $e) {
            Craft::error(
                "Failed to fetch Bluesky thumbnail for entry {$entry->id}: {$e->getMessage()}",
                'standardsite'
            );

            return null;
        }

        if ($bytes === '') {
            return null;
        }

        return strlen($bytes) > self::MAX_THUMB_BYTES ? $this->compress($bytes) : $bytes;
    }

    /**
     * Re-encodes at progressively lower quality until the image fits the blob limit.
     */
    private function compress(string $bytes): ?string
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        try {
            foreach ([70, 50, 30] as $quality) {
                ob_start();
                imagejpeg($image, null, $quality);
                $encoded = (string) ob_get_clean();

                if (strlen($encoded) <= self::MAX_THUMB_BYTES) {
                    return $encoded;
                }
            }
        } finally {
            imagedestroy($image);
        }

        return null;
    }

    private function authenticate(): void
    {
        if ($this->authenticated) {
            return;
        }

        $this->client->authenticate();
        $this->authenticated = true;
    }
}
