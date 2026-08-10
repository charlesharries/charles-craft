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
     * Fetch normalized plays recorded after $afterUri, newest first. A null
     * $afterUri fetches the whole collection.
     *
     * The cutoff is a URI rather than a timestamp because the record key is
     * the only ordering the PDS guarantees: listRecords sorts by key
     * descending (no `reverse` param, which would flip it to oldest-first),
     * keys are TIDs minted when the record is written, and every URI in one
     * repo/collection differs only in that trailing key. `playedTime` does
     * not follow that order - teal.fm backdates plays submitted late, so one
     * can sit mid-listing carrying a timestamp months older than its
     * neighbours, and a sync that stopped there would strand every newer play
     * behind it permanently.
     */
    public function getPlaysAfter(?string $afterUri, ?int $maxPages = 20): array
    {
        $plays = [];
        $cursor = null;

        for ($page = 0; $maxPages === null || $page < $maxPages; $page++) {
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
                $uri = $record['uri'] ?? null;

                if ($afterUri !== null && $uri !== null && $uri <= $afterUri) {
                    $reachedCutoff = true;
                    break;
                }

                $plays[] = self::normalize($record);
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

        return $record ? self::normalize($record) : null;
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
