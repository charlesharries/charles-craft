<?php

namespace tests\helpers\tealfm;

use DateTimeImmutable;
use DateTimeZone;
use helpers\tealfm\ListeningChart;
use PHPUnit\Framework\TestCase;

class ListeningChartTest extends TestCase
{
    /** A play, given as the only thing the chart reads: when it happened. */
    private function play(string $playedTime): DateTimeImmutable
    {
        return new DateTimeImmutable($playedTime, new DateTimeZone('UTC'));
    }

    /**
     * A chart of the days from $start, which is a local midnight - the same
     * thing craft.tracks hands it.
     */
    private function chart(array $plays, int $days = 3, string $start = '2026-08-08', string $zone = 'UTC'): array
    {
        $zone = new DateTimeZone($zone);

        return ListeningChart::of($plays, new DateTimeImmutable($start, $zone), $days, $zone);
    }

    /** What a chart's days are worth, keyed by date. */
    private function counts(array $chart): array
    {
        return array_combine(array_column($chart['days'], 'day'), array_column($chart['days'], 'count'));
    }

    public function test_counts_the_plays_of_each_day(): void
    {
        $chart = $this->chart([
            $this->play('2026-08-08 09:00:00'),
            $this->play('2026-08-08 22:30:00'),
            $this->play('2026-08-10 14:00:00'),
        ]);

        $this->assertSame([
            '2026-08-08' => 2,
            '2026-08-09' => 0,
            '2026-08-10' => 1,
        ], $this->counts($chart));
    }

    public function test_counts_a_day_with_nothing_played_as_a_zero(): void
    {
        // A quiet Sunday is a dip in the line, not a day missing from it.
        $chart = $this->chart([$this->play('2026-08-08 09:00:00')], days: 30);

        $this->assertCount(30, $chart['days']);
        $this->assertSame(1, array_sum(array_column($chart['days'], 'count')));
    }

    public function test_runs_the_days_oldest_first(): void
    {
        $chart = $this->chart([], days: 4);

        $this->assertSame(
            ['2026-08-08', '2026-08-09', '2026-08-10', '2026-08-11'],
            array_column($chart['days'], 'day'),
        );
    }

    public function test_counts_a_play_under_the_day_it_was_played_locally(): void
    {
        // Stored as UTC, so an afternoon play in London is the next day in
        // Sydney - and the chart is drawn for someone reading it there.
        $chart = $this->chart(
            [$this->play('2026-08-08 14:30:00')],
            start: '2026-08-08',
            zone: 'Australia/Sydney',
        );

        $this->assertSame(['2026-08-08' => 0, '2026-08-09' => 1, '2026-08-10' => 0], $this->counts($chart));
    }

    public function test_leaves_out_a_play_from_outside_the_window(): void
    {
        $chart = $this->chart([
            $this->play('2026-08-07 23:00:00'),
            $this->play('2026-08-09 12:00:00'),
            $this->play('2026-08-11 01:00:00'),
        ]);

        $this->assertSame(1, $chart['total']);
    }

    public function test_totals_the_plays_it_charts(): void
    {
        $chart = $this->chart([
            $this->play('2026-08-08 09:00:00'),
            $this->play('2026-08-08 10:00:00'),
            $this->play('2026-08-10 10:00:00'),
        ]);

        $this->assertSame(3, $chart['total']);
        $this->assertSame(2, $chart['max']);
    }

    public function test_plots_the_busiest_day_at_the_top_and_a_silent_one_on_the_baseline(): void
    {
        $chart = $this->chart([
            $this->play('2026-08-08 09:00:00'),
            $this->play('2026-08-10 09:00:00'),
            $this->play('2026-08-10 10:00:00'),
        ]);

        $ys = array_column($chart['days'], 'y');

        $this->assertSame((float) ListeningChart::PADDING, $ys[2]);
        $this->assertSame($chart['baseline'], $ys[1]);
        // ...and a day of half the busiest one's plays, halfway up.
        $this->assertSame(($chart['baseline'] + ListeningChart::PADDING) / 2, $ys[0]);
    }

    public function test_keeps_the_stroke_inside_the_plot(): void
    {
        // A line drawn at the very top or bottom is a line half cut off.
        $chart = $this->chart([$this->play('2026-08-08 09:00:00')]);

        $this->assertGreaterThan(0, min(array_column($chart['days'], 'y')));
        $this->assertLessThan($chart['height'], $chart['baseline']);
    }

    public function test_draws_a_flat_line_when_nothing_was_played(): void
    {
        $chart = $this->chart([]);

        $this->assertSame(0, $chart['total']);
        $this->assertSame(0, $chart['max']);
        $this->assertSame([$chart['baseline']], array_unique(array_column($chart['days'], 'y')));
    }

    public function test_spaces_the_days_evenly_across_the_plot(): void
    {
        $chart = $this->chart([], days: 4);

        $this->assertSame(180.0, $chart['step']);
        // Each day sits in the middle of the column it's hovered by.
        $this->assertSame([90.0, 270.0, 450.0, 630.0], array_column($chart['days'], 'x'));
    }

    public function test_runs_the_line_out_to_both_edges(): void
    {
        // Otherwise it stops half a column short of each end, which reads as
        // the chart being cut off rather than as the month it covers.
        $chart = $this->chart([$this->play('2026-08-08 09:00:00')], days: 2);

        // A day at each end, held level out to the edge either side of them.
        $this->assertSame('0,4 180,4 540,156 720,156', $chart['line']);
    }

    public function test_has_no_line_to_draw_over_no_days(): void
    {
        $chart = $this->chart([], days: 0);

        $this->assertSame([], $chart['days']);
        $this->assertSame('', $chart['line']);
    }
}
