<?php

namespace helpers\tealfm;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A stretch of listening as a grid: a play count per day, one box a day and
 * seven boxes to a column, shaded by how busy the day was.
 *
 * The geometry is worked out here rather than in the template because it's the
 * part worth testing - the template only has to say what colour a level is.
 */
class ListeningChart
{
    /**
     * A day's box and the gap after it. Nothing is measured in pixels: the SVG
     * scales to whatever width it's given, so these are only ever ratios.
     */
    const CELL = 10;

    const GAP = 2;

    /** How many shades a day with anything played can land on. */
    const LEVELS = 4;

    /**
     * The range to read plays out of: $days days back from $end, run back to
     * the Monday on or before that, so the grid opens on a full column rather
     * than on the stray day or two a year starting mid-week leaves behind.
     *
     * Whole days rather than an hour count, so the ones the clocks change on
     * don't shift the window off its Monday.
     *
     * @return array{0: DateTimeImmutable, 1: int}
     */
    public static function window(DateTimeImmutable $end, int $days): array
    {
        $start = $end->modify("-$days days");
        $offset = (int) $start->format('N') - 1;

        return [$start->modify("-$offset days"), $days + $offset];
    }

    /**
     * The grid of the $days days starting at $start, oldest day first.
     *
     * @param DateTimeImmutable[] $playedTimes every play in the window
     * @param DateTimeImmutable $start local midnight on the window's first day
     * @param DateTimeZone $zone the zone the days are reckoned in
     * @return array{
     *     width: int, height: int, cell: int, weeks: int, max: int, total: int,
     *     days: array<int, array{day: string, count: int, level: int, x: int, y: int}>,
     * }
     */
    public static function of(array $playedTimes, DateTimeImmutable $start, int $days, DateTimeZone $zone): array
    {
        $counts = self::counts($playedTimes, $start, $days, $zone);
        $bounds = self::bounds($counts);
        $pitch = self::CELL + self::GAP;
        // Monday is row 0, so a window starting mid-week leaves the top of its
        // first column empty rather than shifting every weekday up a row.
        $offset = (int) $start->setTimezone($zone)->format('N') - 1;
        $weeks = $counts ? intdiv($offset + count($counts) - 1, 7) + 1 : 0;
        $plotted = [];
        $index = 0;

        foreach ($counts as $day => $count) {
            $plotted[] = [
                'day' => $day,
                'count' => $count,
                'level' => self::level($count, $bounds),
                'x' => intdiv($offset + $index, 7) * $pitch,
                'y' => (($offset + $index) % 7) * $pitch,
            ];
            $index++;
        }

        return [
            'width' => max($weeks * $pitch - self::GAP, 0),
            'height' => 7 * $pitch - self::GAP,
            'cell' => self::CELL,
            'weeks' => $weeks,
            'max' => $counts ? max($counts) : 0,
            'total' => array_sum($counts),
            'days' => $plotted,
        ];
    }

    /**
     * A play count for each day of the window, oldest first and keyed by date.
     *
     * Days with nothing played are counted in as zeroes: a quiet Tuesday is a
     * pale box, not a hole in the grid.
     *
     * @param DateTimeImmutable[] $playedTimes
     * @return array<string, int>
     */
    protected static function counts(array $playedTimes, DateTimeImmutable $start, int $days, DateTimeZone $zone): array
    {
        $start = $start->setTimezone($zone);
        $counts = [];

        for ($day = 0; $day < $days; $day++) {
            // Days rather than hours, so the one the clocks change on is still
            // a day long.
            $counts[$start->modify("+$day days")->format('Y-m-d')] = 0;
        }

        foreach ($playedTimes as $time) {
            // Stored as UTC, so a listen at half past midnight belongs to the
            // day it was played locally, not the day the timestamp reads as.
            $day = $time->setTimezone($zone)->format('Y-m-d');

            // A play either side of the window has no box to go in.
            if (isset($counts[$day])) {
                $counts[$day]++;
            }
        }

        return $counts;
    }

    /**
     * The counts each shade starts at: the quartiles of the days that had
     * anything played on them.
     *
     * Quartiles rather than fractions of the busiest day, so one binge doesn't
     * wash the rest of the year out to the palest shade.
     *
     * @param array<string, int> $counts
     * @return int[]
     */
    protected static function bounds(array $counts): array
    {
        $played = array_values(array_filter($counts));
        sort($played);
        $days = count($played);

        if (!$days) {
            return [];
        }

        return array_map(
            fn ($level) => $played[intdiv(($days - 1) * $level, self::LEVELS)],
            range(1, self::LEVELS - 1),
        );
    }

    /**
     * Which shade a count sits on: 0 for a day with nothing played, then 1 to
     * LEVELS as it passes each quartile.
     *
     * Strictly past, so a year of identical days is a flat wash of the palest
     * shade rather than of the darkest.
     *
     * @param int[] $bounds
     */
    protected static function level(int $count, array $bounds): int
    {
        if (!$count) {
            return 0;
        }

        return 1 + count(array_filter($bounds, fn ($bound) => $count > $bound));
    }
}
