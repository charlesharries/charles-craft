<?php

namespace helpers\tealfm;

/**
 * The keys plays are grouped under, wherever listening is being counted.
 *
 * Shared so the charts and the log never disagree about what counts as the
 * same album or the same song.
 */
class PlayKey
{
    /**
     * Identifies the album a play belongs to, by title.
     *
     * Deliberately not by releaseMbId: that identifies a *release*, and one
     * album has many - pressings, regions, reissues. Keying on it splits an
     * album in two, which is exactly what this listening history does with Box
     * for Buddy, Box for Star. Plays teal.fm gave no release for have no album.
     */
    public static function album(array $play): ?string
    {
        return self::normalize($play['releaseName']);
    }

    /**
     * Identifies the song. Keyed on the artist too, because a title on its own
     * would treat every cover and namesake as one song.
     */
    public static function song(array $play): ?string
    {
        $track = self::normalize($play['trackName']);

        return $track === null ? null : $track . "\0" . self::normalize($play['artist']);
    }

    /**
     * A key that ignores the casing and padding teal.fm's metadata varies in,
     * or null when there's nothing left to key on.
     */
    protected static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtolower($value);
    }
}
