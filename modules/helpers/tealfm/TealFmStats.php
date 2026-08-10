<?php

namespace helpers\tealfm;

class TealFmStats
{
    const PERIODS = [
        '7days' => 7,
        '30days' => 30,
    ];

    public static function compute(string $period): array
    {
        $days = self::PERIODS[$period] ?? self::PERIODS['7days'];
        $since = (new \DateTimeImmutable())->modify("-$days days");
        $plays = (new TealFmListenRepository())->forPeriod($since);

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

    // $plays is already newest-first (TealFmListenRepository::forPeriod), so this is
    // just a group + reshape, not a resort. A run of consecutive plays from
    // one album collapses into a single row carrying the run's playCount, so
    // sitting through a whole album doesn't fill the list on its own.
    protected static function recentPlays(array $plays, int $limit = 20): array
    {
        $rows = [];
        $lastKey = null;

        foreach ($plays as $play) {
            $key = self::albumKey($play);

            if ($key !== null && $key === $lastKey) {
                $rows[array_key_last($rows)]['playCount']++;
                continue;
            }

            // Checked after the grouping above so the last row still picks up
            // the rest of its run before we stop.
            if (count($rows) >= $limit) {
                break;
            }

            $rows[] = [
                'trackName' => $play['trackName'],
                'artist' => implode(', ', $play['artistNames']),
                'releaseName' => $play['releaseName'],
                'mbid' => $play['releaseMbId'],
                'playedTime' => $play['playedTime']?->format(DATE_ATOM),
                'playCount' => 1,
            ];

            $lastKey = $key;
        }

        return $rows;
    }

    /**
     * Identifies the album a play belongs to, or null when teal.fm gave us no
     * release at all - those plays never group, even with each other.
     */
    protected static function albumKey(array $play): ?string
    {
        return $play['releaseMbId'] ?: ($play['releaseName'] ?: null);
    }
}
