<?php

namespace helpers\tealfm;

use Craft;

class TealFmClient
{
    protected \GuzzleHttp\Client $client;

    const BASE_URI = 'https://bsky.social/xrpc/';

    const COLLECTION = 'fm.teal.alpha.feed.play';

    public function __construct(protected string $identifier)
    {
        $this->client = Craft::createGuzzleClient(['base_uri' => self::BASE_URI]);
    }

    /**
     * Fetch normalized plays newer than $since, newest first.
     *
     * listRecords' default order (no `reverse` param) is newest-first for
     * this collection, confirmed against the live PDS - `reverse=true`
     * flips it to oldest-first, so it's deliberately omitted here.
     */
    public function getPlaysSince(\DateTimeInterface $since, int $maxPages = 20): array
    {
        $plays = [];
        $cursor = null;

        for ($page = 0; $page < $maxPages; $page++) {
            $query = [
                'repo' => $this->identifier,
                'collection' => self::COLLECTION,
                'limit' => 100,
            ];

            if ($cursor) {
                $query['cursor'] = $cursor;
            }

            $response = $this->client->get('com.atproto.repo.listRecords', ['query' => $query]);
            $data = json_decode($response->getBody(), true);
            $records = $data['records'] ?? [];

            if (empty($records)) {
                break;
            }

            $reachedCutoff = false;

            foreach ($records as $record) {
                $play = self::normalize($record['value'] ?? []);

                if (!$play['playedTime']) {
                    continue;
                }

                if ($play['playedTime'] < $since) {
                    $reachedCutoff = true;
                    break;
                }

                $plays[] = $play;
            }

            if ($reachedCutoff) {
                break;
            }

            $cursor = $data['cursor'] ?? null;

            if (!$cursor) {
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
        $response = $this->client->get('com.atproto.repo.listRecords', [
            'query' => [
                'repo' => $this->identifier,
                'collection' => self::COLLECTION,
                'limit' => 1,
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        $record = $data['records'][0] ?? null;

        return $record ? self::normalize($record['value']) : null;
    }

    protected static function normalize(array $value): array
    {
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
            'trackName' => $value['trackName'] ?? null,
            'artistNames' => $artistNames,
            'releaseName' => $value['releaseName'] ?? null,
            'releaseMbId' => $releaseMbId,
            'playedTime' => $playedTime,
        ];
    }
}
