<?php

namespace helpers\tealfm;

use DateTimeZone;

/**
 * Reads a stretch of plays back as a diary: a day at a time, a row per sitting
 * rather than a row per play.
 */
class ListeningLog
{
    const ALBUM = 'album';

    const SONG = 'song';

    const SONGS = 'songs';

    /** A run touching this many of a release's tracks reads as an album. */
    const ALBUM_AFTER_TRACKS = 2;

    /**
     * Groups $plays by the day they were played, newest first.
     *
     * @param array<int, array> $plays newest first, as hydrated by TealFmListenRepository
     * @param DateTimeZone $zone the zone the days are reckoned in
     * @return array<int, array{day: string, rows: array<int, array{type: string, listens: array<int, array>}>}>
     */
    public static function days(array $plays, DateTimeZone $zone): array
    {
        $days = [];

        foreach ($plays as $play) {
            // playedTime is stored - and read back - as UTC, so a listen at
            // half past midnight is filed under the wrong day unless the
            // reader's zone gets a say.
            $days[$play['playedTime']->setTimezone($zone)->format('Y-m-d')][] = $play;
        }

        return array_map(
            fn ($day, $plays) => [
                'day' => $day,
                'total' => count($plays), 
                'rows' => self::rows(self::collapse($plays))
            ],
            array_keys($days),
            $days,
        );
    }

    /**
     * A day's listens as the rows the log prints.
     *
     * An album is a row to itself. Everything played between two albums shares
     * one - which is the whole point of the row layer: a row is a thing worth
     * looking at, and a hundred singles are one thing.
     *
     * @param array<int, array> $entries
     * @return array<int, array{type: string, listens: array<int, array>}>
     */
    protected static function rows(array $entries): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $last = array_key_last($rows);

            if ($entry['type'] === self::ALBUM) {
                $rows[] = ['type' => self::ALBUM, 'listens' => [$entry]];
            } elseif ($last !== null && $rows[$last]['type'] === self::SONGS) {
                $rows[$last]['listens'][] = $entry;
            } else {
                $rows[] = ['type' => self::SONGS, 'listens' => [$entry]];
            }
        }

        return $rows;
    }

    /**
     * A day's plays as the sittings they came in.
     *
     * @param array<int, array> $plays newest first
     * @return array<int, array>
     */
    protected static function collapse(array $plays): array
    {
        $entries = [];
        $run = [];
        $key = null;

        foreach ($plays as $play) {
            $playKey = self::key($play);

            if ($run && $playKey !== $key) {
                $entries[] = self::entry($run);
                $run = [];
            }

            $key = $playKey;
            $run[] = $play;
        }

        if ($run) {
            $entries[] = self::entry($run);
        }

        return $entries;
    }

    /**
     * What a play has to share with the one before it to belong to the same
     * run: the album, or - for a single, which has none - the song itself, so
     * a track played four times over is one row saying so.
     */
    protected static function key(array $play): string
    {
        // The uri is the last resort for a play too threadbare to key on
        // either, and being unique, it leaves that play standing alone.
        return PlayKey::album($play) ?? PlayKey::song($play) ?? $play['uri'];
    }

    /**
     * One row, standing for a run of plays.
     *
     * The run is read newest first, so its first play both represents it and
     * dates it - the log is ordered by when a sitting finished, which is where
     * the next one starts.
     *
     * @param array<int, array> $run
     */
    protected static function entry(array $run): array
    {
        $tracks = [];
        $releaseMbIds = [];

        foreach ($run as $play) {
            $tracks[PlayKey::song($play) ?? $play['uri']] = true;

            if ($play['releaseMbId']) {
                // Keyed, so a release seen a dozen times is still one id.
                $releaseMbIds[$play['releaseMbId']] = true;
            }
        }

        return [
            ...$run[0],
            'type' => count($tracks) >= self::ALBUM_AFTER_TRACKS ? self::ALBUM : self::SONG,
            'trackCount' => count($tracks),
            'playCount' => count($run),
            // Cover art is stored per release, and one album arrives under
            // several - the sync may only have reached some of them.
            'releaseMbIds' => array_keys($releaseMbIds),
        ];
    }
}
