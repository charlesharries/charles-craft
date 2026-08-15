<?php

namespace helpers\tealfm;

use Craft;

class TealFmClient
{
    protected \GuzzleHttp\Client $client;

    const BASE_URI = 'https://bsky.social/xrpc/';

    /**
     * teal.fm's play lexicon graduated out of alpha, so a repo holds plays
     * under both NSIDs.
     */
    const COLLECTIONS = ['fm.teal.feed.play', 'fm.teal.alpha.feed.play'];

    public function __construct(protected string $identifier)
    {
        $this->client = Craft::createGuzzleClient(['base_uri' => self::BASE_URI]);
    }

    /**
     * Fetch normalized plays from every collection, each picking up after its
     * own entry in $afterUris.
     *
     * @param array<string, string|null> $afterUris keyed by collection
     */
    public function getPlaysAfter(array $afterUris, ?int $maxPages = 20): array
    {
        $plays = [];

        foreach (self::COLLECTIONS as $collection) {
            $plays = array_merge(
                $plays,
                $this->getCollectionPlaysAfter($collection, $afterUris[$collection] ?? null, $maxPages),
            );
        }

        return $plays;
    }

    /**
     * Fetch normalized plays from a single collection, newest first. A null
     * $afterUri fetches the whole collection.
     */
    protected function getCollectionPlaysAfter(string $collection, ?string $afterUri, ?int $maxPages): array
    {
        $plays = [];
        $cursor = null;

        for ($page = 0; $maxPages === null || $page < $maxPages; $page++) {
            $data = $this->listRecords($collection, $cursor);
            $records = $data['records'] ?? [];

            if (empty($records)) {
                break;
            }

            [$pagePlays, $reachedCutoff] = self::normalizeUntil($records, $afterUri);
            $plays = array_merge($plays, $pagePlays);

            $cursor = $data['cursor'] ?? null;

            if ($reachedCutoff || !$cursor) {
                break;
            }
        }

        return $plays;
    }

    /**
     * Fetch the single most recent play, or null if there isn't one.
     */
    public function getLatestPlay(): ?array
    {
        $latest = null;

        foreach (self::COLLECTIONS as $collection) {
            $data = $this->listRecords($collection, limit: 1);
            $record = $data['records'][0] ?? null;

            if (!$record) {
                continue;
            }

            $play = self::normalize($record);

            if (self::isNewer($play, $latest)) {
                $latest = $play;
            }
        }

        return $latest;
    }

    protected static function isNewer(array $play, ?array $latest): bool
    {
        if ($latest === null) {
            return true;
        }

        if (!$play['playedTime']) {
            return false;
        }

        return !$latest['playedTime'] || $play['playedTime'] > $latest['playedTime'];
    }

    /**
     * Fetch a single page of raw records, newest first.
     */
    protected function listRecords(string $collection, ?string $cursor = null, int $limit = 100): array
    {
        $query = [
            'repo' => $this->identifier,
            'collection' => $collection,
            'limit' => $limit,
        ];

        if ($cursor) {
            $query['cursor'] = $cursor;
        }

        $response = $this->client->get('com.atproto.repo.listRecords', ['query' => $query]);

        return json_decode($response->getBody(), true) ?? [];
    }

    /**
     * Normalize records until one at or older than $afterUri shows up. Returns
     * the plays collected and whether that cutoff was reached.
     */
    protected static function normalizeUntil(array $records, ?string $afterUri): array
    {
        $plays = [];

        foreach ($records as $record) {
            if (self::isAtOrBefore($record, $afterUri)) {
                return [$plays, true];
            }

            $plays[] = self::normalize($record);
        }

        return [$plays, false];
    }

    protected static function isAtOrBefore(array $record, ?string $afterUri): bool
    {
        $uri = $record['uri'] ?? null;

        return $afterUri !== null && $uri !== null && $uri <= $afterUri;
    }

    protected static function normalize(array $record): array
    {
        $value = $record['value'] ?? [];

        if (!empty($value['artists'])) {
            $artistNames = array_map(fn ($artist) => $artist['artistName'], $value['artists']);
        } else {
            $artistNames = $value['artistNames'] ?? [];
        }

        $playedTime = null;

        if (!empty($value['playedTime'])) {
            try {
                $playedTime = new \DateTimeImmutable($value['playedTime']);
            } catch (\Exception $e) {
                $playedTime = null;
            }
        }

        // The lexicon documents this as a `mbid:<uuid>` URI, but records seen
        // in the wild store a bare UUID - strip the prefix if it's there.
        $releaseMbId = $value['releaseMbId'] ?? null;
        if ($releaseMbId && str_starts_with($releaseMbId, 'mbid:')) {
            $releaseMbId = substr($releaseMbId, 5);
        }

        return [
            'uri' => $record['uri'] ?? null,
            'trackName' => $value['trackName'] ?? null,
            'artistNames' => $artistNames,
            'releaseName' => $value['releaseName'] ?? null,
            'releaseMbId' => $releaseMbId,
            'playedTime' => $playedTime,
        ];
    }
}
