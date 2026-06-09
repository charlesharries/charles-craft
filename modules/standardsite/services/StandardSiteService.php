<?php

namespace modules\standardsite\services;

use Craft;
use craft\elements\Entry;
use craft\elements\Tag;

class StandardSiteService
{
    private AtProtoClient $client;

    public const SUPPORTED_SECTIONS = ['posts', 'stream', 'books', 'walks'];
    private const PUBLICATION_COLLECTION = 'site.standard.publication';
    private const DOCUMENT_COLLECTION = 'site.standard.document';
    private const PUBLICATION_RKEY = 'self';

    public function __construct()
    {
        $this->client = new AtProtoClient();
    }

    public function authenticate(): void
    {
        $this->client->authenticate();
    }

    public function createOrUpdatePublication(): string
    {
        $record = [
            '$type' => self::PUBLICATION_COLLECTION,
            'url' => 'https://charlesharri.es',
            'name' => 'Charles Harries',
            'description' => "I'm a software developer working on the web in the North East of England.",
            'createdAt' => date('c'),
        ];

        $result = $this->client->putRecord(
            self::PUBLICATION_COLLECTION,
            self::PUBLICATION_RKEY,
            $record
        );

        $uri = $result['uri'];
        Craft::$app->projectConfig->set('standardsite.publicationUri', $uri);

        return $uri;
    }

    public function getPublicationUri(): ?string
    {
        return Craft::$app->projectConfig->get('standardsite.publicationUri');
    }

    public static function documentUriForEntry(Entry $entry): ?string
    {
        $publicationUri = Craft::$app->projectConfig->get('standardsite.publicationUri');
        if (!$publicationUri) {
            return null;
        }

        $parts = explode('/', $publicationUri);
        $did = $parts[2] ?? null;
        if (!$did) {
            return null;
        }

        $rkey = self::tidForEntry($entry);
        return 'at://' . $did . '/' . self::DOCUMENT_COLLECTION . '/' . $rkey;
    }

    public static function tidForEntry(Entry $entry): string
    {
        $timestampMicros = $entry->postDate->getTimestamp() * 1000000;
        $clockId = $entry->id % 1024;
        $value = ($timestampMicros << 10) | $clockId;

        return self::encodeTid($value);
    }

    private static function encodeTid(int $value): string
    {
        $charset = '234567abcdefghijklmnopqrstuvwxyz';
        $result = '';

        for ($i = 0; $i < 13; $i++) {
            $result = $charset[$value & 0x1f] . $result;
            $value >>= 5;
        }

        return $result;
    }

    public function createOrUpdateDocument(Entry $entry): string
    {
        $publicationUri = $this->getPublicationUri();
        if (!$publicationUri) {
            throw new \RuntimeException('Publication not set up yet. Run standardsite/standard-site/setup first.');
        }

        $rkey = $this->rKeyForEntry($entry);

        $record = [
            '$type' => self::DOCUMENT_COLLECTION,
            'site' => $publicationUri,
            'title' => $entry->title,
            'publishedAt' => $entry->postDate->format('c'),
            'path' => $this->pathForEntry($entry),
        ];

        if ($entry->summary) {
            $record['description'] = $entry->summary;
        }

        $textContent = $this->plainTextContent($entry);
        if ($textContent) {
            $record['textContent'] = $textContent;
        }

        $tags = $this->tagsForEntry($entry);
        if (!empty($tags)) {
            $record['tags'] = $tags;
        }

        if ($entry->dateUpdated && $entry->dateUpdated > $entry->postDate) {
            $record['updatedAt'] = $entry->dateUpdated->format('c');
        }

        $record['createdAt'] = $entry->postDate->format('c');

        $result = $this->client->putRecord(
            self::DOCUMENT_COLLECTION,
            $rkey,
            $record
        );

        return $result['uri'];
    }

    private function rKeyForEntry(Entry $entry): string
    {
        return self::tidForEntry($entry);
    }

    private function pathForEntry(Entry $entry): string
    {
        $url = $entry->getUrl();
        if (!$url) {
            return '/' . $entry->section->handle . '/' . $entry->slug;
        }
        return parse_url($url, PHP_URL_PATH);
    }

    private function plainTextContent(Entry $entry): ?string
    {
        $body = $entry->body ?? null;
        if (!$body) {
            return null;
        }

        $text = strip_tags($body);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function tagsForEntry(Entry $entry): array
    {
        if (!$entry->tags) {
            return [];
        }

        return array_map(
            fn(Tag $tag) => $tag->title,
            $entry->tags->all()
        );
    }
}
