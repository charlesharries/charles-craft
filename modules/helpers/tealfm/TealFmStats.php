<?php

namespace helpers\tealfm;

class TealFmStats
{
    const PERIODS = [
        '7days' => 7,
        '30days' => 30,
    ];

    // A long safety-net TTL for the request-path cache read - it's not what
    // keeps this data fresh (RefreshTealFmStatsJob does that, every ~5
    // minutes), it just guarantees a request is never left blocking on a
    // live fetch if the job has fallen behind.
    const CACHE_DURATION = 86400;

    public static function cacheKey(string $period): string
    {
        return "teal-fm-stats:$period";
    }

    public static function compute(string $identifier, string $period): array
    {
        $days = self::PERIODS[$period] ?? self::PERIODS['7days'];
        $since = (new \DateTimeImmutable())->modify("-$days days");
        $plays = (new TealFmClient($identifier))->getPlaysSince($since);

        $artistCounts = [];
        $albums = [];

        foreach ($plays as $play) {
            foreach ($play['artistNames'] as $artistName) {
                $artistCounts[$artistName] = ($artistCounts[$artistName] ?? 0) + 1;
            }

            if (empty($play['releaseName'])) {
                continue;
            }

            $album = $albums[$play['releaseName']] ??= [
                'name' => $play['releaseName'],
                'artist' => implode(', ', $play['artistNames']),
                'mbid' => $play['releaseMbId'],
                'playCount' => 0,
            ];

            $album['playCount']++;
            $albums[$play['releaseName']] = $album;
        }

        return [
            'totals' => [
                'songs' => count($plays),
                'artists' => count($artistCounts),
                'albums' => count($albums),
            ],
            'artists' => self::topEntries($artistCounts),
            'albums' => self::topAlbums($albums),
            'recent' => self::recentPlays($plays),
        ];
    }

    protected static function topEntries(array $counts, int $limit = 10): array
    {
        arsort($counts);

        $entries = [];

        foreach (array_slice($counts, 0, $limit, true) as $name => $playCount) {
            $entries[] = ['name' => $name, 'playCount' => $playCount];
        }

        return $entries;
    }

    protected static function topAlbums(array $albums, int $limit = 10): array
    {
        usort($albums, fn ($a, $b) => $b['playCount'] <=> $a['playCount']);

        return array_slice($albums, 0, $limit);
    }

    // $plays is already newest-first (TealFmClient::getPlaysSince), so this
    // is just a slice + reshape, not a resort.
    protected static function recentPlays(array $plays, int $limit = 20): array
    {
        return array_map(fn ($play) => [
            'trackName' => $play['trackName'],
            'artist' => implode(', ', $play['artistNames']),
            'releaseName' => $play['releaseName'],
            'mbid' => $play['releaseMbId'],
            'playedTime' => $play['playedTime']?->format(DATE_ATOM),
        ], array_slice($plays, 0, $limit));
    }
}
