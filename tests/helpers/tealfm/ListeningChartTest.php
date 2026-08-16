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

    /**
     * A chart of days worth the given counts, one day each from $start.
     *
     * @param int[] $counts
     */
    private function chartOfCounts(array $counts, string $start = '2026-08-08'): array
    {
        $plays = [];

        foreach ($counts as $day => $count) {
            for ($play = 0; $play < $count; $play++) {
                $plays[] = $this->play((new DateTimeImmutable($start, new DateTimeZone('UTC')))
                    ->modify("+$day days")
                    ->format('Y-m-d 12:00:00'));
            }
        }

        return $this->chart($plays, days: count($counts), start: $start);
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
        // A quiet Sunday is a pale box, not a hole in the grid.
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

    /** The window for a year ending the morning after $today. */
    private function window(string $today, int $days = 365): array
    {
        $end = (new DateTimeImmutable($today, new DateTimeZone('UTC')))->modify('+1 day');
        [$start, $days] = ListeningChart::window($end, $days);

        return [$start->format('Y-m-d'), $days];
    }

    public function test_opens_the_window_on_a_monday(): void
    {
        // A year back from Sunday the 16th lands on a Sunday, which would leave
        // one lone box in the first column.
        $this->assertSame(['2025-08-11', 371], $this->window('2026-08-16'));
    }

    public function test_leaves_a_window_that_already_opens_on_a_monday_alone(): void
    {
        $this->assertSame(['2025-08-11', 365], $this->window('2026-08-10'));
    }

    public function test_never_opens_the_window_on_less_than_the_days_asked_for(): void
    {
        $day = new DateTimeImmutable('2026-08-10', new DateTimeZone('UTC'));

        for ($i = 0; $i < 7; $i++) {
            [$start, $days] = $this->window($day->modify("+$i days")->format('Y-m-d'));

            $this->assertSame('Mon', date('D', strtotime($start)));
            $this->assertGreaterThanOrEqual(365, $days);
            $this->assertLessThan(372, $days);
        }
    }

    public function test_fills_the_first_column_of_a_windowed_year(): void
    {
        // The whole point of the alignment: 53 full columns, nothing stray.
        [$start, $days] = ListeningChart::window(
            new DateTimeImmutable('2026-08-17', new DateTimeZone('UTC')),
            365,
        );
        $chart = ListeningChart::of([], $start, $days, new DateTimeZone('UTC'));

        $this->assertSame(53, $chart['weeks']);
        $this->assertSame(371, count($chart['days']));
        $this->assertSame(range(0, 6 * (ListeningChart::CELL + ListeningChart::GAP), ListeningChart::CELL + ListeningChart::GAP),
            array_column(array_slice($chart['days'], 0, 7), 'y'));
    }

    public function test_starts_each_column_on_a_monday(): void
    {
        // The 8th is a Saturday, so it belongs in the sixth row of its column
        // rather than the first.
        $chart = $this->chart([], days: 3, start: '2026-08-08');
        $pitch = ListeningChart::CELL + ListeningChart::GAP;

        $this->assertSame([5 * $pitch, 6 * $pitch, 0], array_column($chart['days'], 'y'));
    }

    public function test_runs_a_week_down_a_column_then_across(): void
    {
        // The 10th is a Monday, so it opens a column of its own.
        $chart = $this->chart([], days: 8, start: '2026-08-10');
        $pitch = ListeningChart::CELL + ListeningChart::GAP;

        $this->assertSame([0, 0, 0, 0, 0, 0, 0, $pitch], array_column($chart['days'], 'x'));
        $this->assertSame(range(0, 6 * $pitch, $pitch), array_slice(array_column($chart['days'], 'y'), 0, 7));
        $this->assertSame(0, $chart['days'][7]['y']);
    }

    public function test_sizes_the_grid_to_the_weeks_it_spans(): void
    {
        // A year from a Sunday takes a column for that Sunday alone, and 53 in all.
        $chart = $this->chart([], days: 365, start: '2025-08-17');
        $pitch = ListeningChart::CELL + ListeningChart::GAP;

        $this->assertSame(53, $chart['weeks']);
        $this->assertSame(53 * $pitch - ListeningChart::GAP, $chart['width']);
        $this->assertSame(7 * $pitch - ListeningChart::GAP, $chart['height']);
        $this->assertSame(ListeningChart::CELL, $chart['cell']);
    }

    public function test_darkens_a_day_with_more_plays(): void
    {
        $chart = $this->chartOfCounts([1, 5, 20, 60]);

        $this->assertSame([1, 2, 3, 4], array_column($chart['days'], 'level'));
    }

    public function test_puts_a_day_with_nothing_played_at_its_own_level(): void
    {
        $chart = $this->chartOfCounts([0, 1, 0, 60]);

        $this->assertSame([0, 1, 0, 4], array_column($chart['days'], 'level'));
    }

    public function test_spreads_the_levels_evenly_over_the_days(): void
    {
        // The point of quartiles: a busy day being ten times a quiet one
        // shouldn't leave nine tenths of the grid on one shade.
        $chart = $this->chartOfCounts(range(1, 100));
        $levels = array_count_values(array_column($chart['days'], 'level'));
        ksort($levels);

        $this->assertSame([1, 2, 3, 4], array_keys($levels));

        foreach ($levels as $days) {
            $this->assertEqualsWithDelta(25, $days, 2);
        }
    }

    public function test_keeps_the_scale_off_the_top_when_every_day_is_the_same(): void
    {
        // A year of one play a day is a flat wash, and the palest one reads as
        // that far better than the darkest does.
        $chart = $this->chartOfCounts(array_fill(0, 10, 1));

        $this->assertSame([1], array_unique(array_column($chart['days'], 'level')));
    }

    public function test_reaches_the_top_level_for_the_busiest_day(): void
    {
        // Even where there are only two days to tell apart.
        $chart = $this->chartOfCounts([1, 2]);

        $this->assertSame([1, 4], array_column($chart['days'], 'level'));
    }

    public function test_has_nothing_to_draw_over_no_days(): void
    {
        $chart = $this->chart([], days: 0);

        $this->assertSame([], $chart['days']);
        $this->assertSame(0, $chart['weeks']);
        $this->assertSame(0, $chart['width']);
    }
}
