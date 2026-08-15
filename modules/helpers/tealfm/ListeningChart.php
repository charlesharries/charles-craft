<?php

namespace helpers\tealfm;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A stretch of listening as one line: a play count per day, and the geometry
 * to draw them with.
 *
 * The geometry is worked out here rather than in the template because it's the
 * part worth testing - the template only has to say what colour the line is.
 */
class ListeningChart
{
    /**
     * The coordinate space the line is drawn in. Nothing is measured in pixels:
     * the SVG stretches to whatever width it's given, so these are only ever
     * ratios to each other.
     */
    const WIDTH = 720;

    const HEIGHT = 160;

    /** Room at the top and bottom for the stroke to sit in without clipping. */
    const PADDING = 4;

    /**
     * The chart of the $days days starting at $start, oldest day first.
     *
     * @param DateTimeImmutable[] $playedTimes every play in the window
     * @param DateTimeImmutable $start local midnight on the window's first day
     * @param DateTimeZone $zone the zone the days are reckoned in
     * @return array{
     *     width: int, height: int, baseline: float, step: float,
     *     max: int, total: int, line: string,
     *     days: array<int, array{day: string, count: int, x: float, y: float}>,
     * }
     */
    public static function of(array $playedTimes, DateTimeImmutable $start, int $days, DateTimeZone $zone): array
    {
        $counts = self::counts($playedTimes, $start, $days, $zone);
        $max = $counts ? max($counts) : 0;
        // A month of silence still draws a line, along the bottom.
        $step = self::WIDTH / max(count($counts), 1);
        $plotted = [];
        $column = 0;

        foreach ($counts as $day => $count) {
            $plotted[] = [
                'day' => $day,
                'count' => $count,
                // A day is a column, and its point sits in the middle of it.
                'x' => round(($column + 0.5) * $step, 2),
                'y' => self::y($count, $max),
            ];
            $column++;
        }

        return [
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'baseline' => self::y(0, $max),
            'step' => round($step, 2),
            'max' => $max,
            'total' => array_sum($counts),
            'line' => self::line($plotted),
            'days' => $plotted,
        ];
    }

    /**
     * A play count for each day of the window, oldest first and keyed by date.
     *
     * Days with nothing played are counted in as zeroes: a quiet Tuesday is a
     * dip in the line, not a day missing from it.
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

            // A play either side of the window has no column to go in.
            if (isset($counts[$day])) {
                $counts[$day]++;
            }
        }

        return $counts;
    }

    /**
     * Where a count sits vertically: the tallest day at the top of the plot,
     * a day with nothing played on the baseline.
     */
    protected static function y(int $count, int $max): float
    {
        $plot = self::HEIGHT - 2 * self::PADDING;

        return round(self::HEIGHT - self::PADDING - $count / max($max, 1) * $plot, 2);
    }

    /**
     * The plotted days as a polyline, run out to both edges so the line spans
     * the full width rather than stopping half a column short of it.
     *
     * @param array<int, array{x: float, y: float}> $days
     */
    protected static function line(array $days): string
    {
        if (!$days) {
            return '';
        }

        $points = array_map(fn ($day) => "{$day['x']},{$day['y']}", $days);

        return implode(' ', [
            '0,' . $days[array_key_first($days)]['y'],
            ...$points,
            self::WIDTH . ',' . $days[array_key_last($days)]['y'],
        ]);
    }
}
