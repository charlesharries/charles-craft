<?php

namespace tests\helpers\tealfm;

use DateTimeImmutable;
use DateTimeZone;
use helpers\tealfm\ListeningLog;
use PHPUnit\Framework\TestCase;

class ListeningLogTest extends TestCase
{
    private function play(
        string $trackName,
        string $playedTime = '2026-08-10 22:30:00',
        string $artist = 'Wolf Alice',
        ?string $release = 'Blue Weekend',
        ?string $mbid = null,
    ): array {
        return [
            'uri' => 'at://did:plc:abc/fm.teal.alpha.feed.play/' . md5($trackName . $artist . $playedTime),
            'trackName' => $trackName,
            'artistNames' => [$artist],
            'artist' => $artist,
            'releaseName' => $release,
            'releaseMbId' => $mbid,
            'playedTime' => new DateTimeImmutable($playedTime, new DateTimeZone('UTC')),
        ];
    }

    private function days(array $plays, string $zone = 'UTC'): array
    {
        return ListeningLog::days($plays, new DateTimeZone($zone));
    }

    /** The rows of a log with only one day in it. */
    private function rows(array $plays, string $zone = 'UTC'): array
    {
        $days = $this->days($plays, $zone);

        $this->assertCount(1, $days);

        return $days[0]['rows'];
    }

    /** ...and its listens, for the tests that don't care how they're rowed. */
    private function entries(array $plays, string $zone = 'UTC'): array
    {
        $rows = $this->rows($plays, $zone);

        return $rows ? array_merge(...array_column($rows, 'listens')) : [];
    }

    public function test_collapses_a_run_off_one_album_into_a_single_entry(): void
    {
        $entries = $this->entries([
            $this->play('The Beach II', '2026-08-10 22:30:00'),
            $this->play('Smile', '2026-08-10 22:26:00'),
            $this->play('Delicious Things', '2026-08-10 22:22:00'),
        ]);

        $this->assertCount(1, $entries);
        $this->assertSame(ListeningLog::ALBUM, $entries[0]['type']);
        $this->assertSame('Blue Weekend', $entries[0]['releaseName']);
        $this->assertSame(3, $entries[0]['trackCount']);
    }

    public function test_dates_an_album_entry_by_its_most_recent_play(): void
    {
        $entries = $this->entries([
            $this->play('The Beach II', '2026-08-10 22:30:00'),
            $this->play('Smile', '2026-08-10 22:26:00'),
        ]);

        $this->assertSame('2026-08-10T22:30:00+00:00', $entries[0]['playedTime']->format(DATE_ATOM));
    }

    public function test_shows_a_song_played_on_its_own_as_its_own_entry(): void
    {
        $entries = $this->entries([
            $this->play('Sprained Ankle', '2026-08-10 22:30:00', 'Julien Baker', 'Sprained Ankle'),
            $this->play('Some Single', '2026-08-10 22:26:00', release: null),
        ]);

        $this->assertSame([ListeningLog::SONG, ListeningLog::SONG], array_column($entries, 'type'));
        $this->assertSame(['Sprained Ankle', 'Some Single'], array_column($entries, 'trackName'));
    }

    public function test_keeps_two_sittings_with_one_album_apart(): void
    {
        // The same album twice in a day is two listens, and the log is there to
        // say when - totalling them over the day would lose that.
        $entries = $this->entries([
            $this->play('Smile', '2026-08-10 22:30:00'),
            $this->play('Delicious Things', '2026-08-10 22:26:00'),
            $this->play('Sprained Ankle', '2026-08-10 14:00:00', 'Julien Baker', 'Sprained Ankle'),
            $this->play('The Beach', '2026-08-10 09:30:00'),
            $this->play('Feeling Myself', '2026-08-10 09:26:00'),
        ]);

        $this->assertSame(['Blue Weekend', 'Sprained Ankle', 'Blue Weekend'], array_column($entries, 'releaseName'));
        $this->assertSame([2, 1, 2], array_column($entries, 'trackCount'));
    }

    public function test_counts_a_repeated_track_once_towards_an_album(): void
    {
        $entries = $this->entries([
            $this->play('Smile', '2026-08-10 22:34:00'),
            $this->play('Smile', '2026-08-10 22:30:00'),
            $this->play('Delicious Things', '2026-08-10 22:26:00'),
        ]);

        $this->assertSame(ListeningLog::ALBUM, $entries[0]['type']);
        $this->assertSame(2, $entries[0]['trackCount']);
        $this->assertSame(3, $entries[0]['playCount']);
    }

    public function test_collapses_a_song_played_over_and_over_into_one_entry(): void
    {
        $entries = $this->entries([
            $this->play('Some Single', '2026-08-10 22:34:00', release: null),
            $this->play('Some Single', '2026-08-10 22:30:00', release: null),
            $this->play('Some Single', '2026-08-10 22:26:00', release: null),
        ]);

        $this->assertCount(1, $entries);
        $this->assertSame(ListeningLog::SONG, $entries[0]['type']);
        $this->assertSame(3, $entries[0]['playCount']);
    }

    public function test_reads_a_single_track_off_an_album_as_a_song(): void
    {
        // One track is not an album listen, however much of an album it's off.
        $entries = $this->entries([
            $this->play('Smile', '2026-08-10 22:30:00'),
            $this->play('Sprained Ankle', '2026-08-10 22:26:00', 'Julien Baker', 'Sprained Ankle'),
        ]);

        $this->assertSame([ListeningLog::SONG, ListeningLog::SONG], array_column($entries, 'type'));
        $this->assertSame(['Smile', 'Sprained Ankle'], array_column($entries, 'trackName'));
    }

    public function test_does_not_merge_songs_that_only_share_a_title(): void
    {
        $entries = $this->entries([
            $this->play('Hallelujah', '2026-08-10 22:30:00', 'Leonard Cohen', release: null),
            $this->play('Hallelujah', '2026-08-10 22:26:00', 'Jeff Buckley', release: null),
        ]);

        $this->assertCount(2, $entries);
    }

    public function test_ignores_casing_and_padding_in_album_titles(): void
    {
        $entries = $this->entries([
            $this->play('Smile', '2026-08-10 22:30:00', release: 'Blue Weekend'),
            $this->play('Delicious Things', '2026-08-10 22:26:00', release: '  BLUE WEEKEND'),
        ]);

        $this->assertCount(1, $entries);
        // The first spelling seen is the one shown.
        $this->assertSame('Blue Weekend', $entries[0]['releaseName']);
    }

    public function test_gathers_every_release_an_album_was_played_under(): void
    {
        // Cover art is stored per release, and the sync may only have reached
        // one of them - so the entry has to carry all of them.
        $entries = $this->entries([
            $this->play('Smile', '2026-08-10 22:30:00', mbid: 'aaaaaaaa-5b1e-4f5a-9c9a-1a2b3c4d5e6f'),
            $this->play('Delicious Things', '2026-08-10 22:26:00', mbid: 'bbbbbbbb-5b1e-4f5a-9c9a-1a2b3c4d5e6f'),
            $this->play('The Beach', '2026-08-10 22:22:00', mbid: 'aaaaaaaa-5b1e-4f5a-9c9a-1a2b3c4d5e6f'),
        ]);

        $this->assertSame([
            'aaaaaaaa-5b1e-4f5a-9c9a-1a2b3c4d5e6f',
            'bbbbbbbb-5b1e-4f5a-9c9a-1a2b3c4d5e6f',
        ], $entries[0]['releaseMbIds']);
    }

    public function test_gathers_no_releases_for_a_play_that_has_none(): void
    {
        $entries = $this->entries([$this->play('Some Single', release: null)]);

        $this->assertSame([], $entries[0]['releaseMbIds']);
    }

    public function test_groups_plays_by_day_newest_first(): void
    {
        $days = $this->days([
            $this->play('Smile', '2026-08-10 22:30:00'),
            $this->play('Sprained Ankle', '2026-08-09 14:00:00', 'Julien Baker', 'Sprained Ankle'),
            $this->play('Some Single', '2026-08-08 09:00:00', release: null),
        ]);

        $this->assertSame(['2026-08-10', '2026-08-09', '2026-08-08'], array_column($days, 'day'));
        $this->assertSame([1, 1, 1], array_map(fn ($day) => count($day['rows']), $days));
    }

    public function test_does_not_run_an_album_across_a_day_boundary(): void
    {
        $days = $this->days([
            $this->play('Smile', '2026-08-11 00:10:00'),
            $this->play('Delicious Things', '2026-08-10 23:50:00'),
        ]);

        $this->assertSame(['2026-08-11', '2026-08-10'], array_column($days, 'day'));
    }

    public function test_files_a_play_under_the_day_it_was_played_locally(): void
    {
        // Stored as UTC, so a listen at half past midnight in Sydney belongs to
        // the day after the one the timestamp reads as.
        $days = $this->days([
            $this->play('Smile', '2026-08-10 14:30:00'),
        ], 'Australia/Sydney');

        $this->assertSame(['2026-08-11'], array_column($days, 'day'));
    }

    public function test_gathers_the_songs_between_two_albums_into_one_row(): void
    {
        $rows = $this->rows([
            $this->play('Smile', '2026-08-10 22:30:00'),
            $this->play('Delicious Things', '2026-08-10 22:26:00'),
            $this->play('Some Single', '2026-08-10 22:22:00', release: null),
            $this->play('Another Single', '2026-08-10 22:18:00', 'CCFX', release: null),
            $this->play('Sprained Ankle', '2026-08-10 22:14:00', 'Julien Baker', 'Sprained Ankle'),
            $this->play('Appointments', '2026-08-10 22:10:00', 'Julien Baker', 'Sprained Ankle'),
        ]);

        $this->assertSame([ListeningLog::ALBUM, ListeningLog::SONGS, ListeningLog::ALBUM], array_column($rows, 'type'));
        $this->assertSame(
            ['Some Single', 'Another Single'],
            array_column($rows[1]['listens'], 'trackName'),
        );
    }

    public function test_gives_an_album_a_row_to_itself(): void
    {
        $rows = $this->rows([
            $this->play('Smile', '2026-08-10 22:30:00'),
            $this->play('Delicious Things', '2026-08-10 22:26:00'),
        ]);

        $this->assertCount(1, $rows);
        $this->assertCount(1, $rows[0]['listens']);
    }

    public function test_starts_a_new_row_of_songs_after_each_album(): void
    {
        // Songs either side of an album are two shuffles, not one - the album
        // between them is the whole reason the log is ordered.
        $rows = $this->rows([
            $this->play('Some Single', '2026-08-10 22:30:00', release: null),
            $this->play('Smile', '2026-08-10 22:26:00'),
            $this->play('Delicious Things', '2026-08-10 22:22:00'),
            $this->play('Another Single', '2026-08-10 22:18:00', 'CCFX', release: null),
        ]);

        $this->assertSame([ListeningLog::SONGS, ListeningLog::ALBUM, ListeningLog::SONGS], array_column($rows, 'type'));
    }

    public function test_gathers_a_whole_day_of_shuffling_into_one_row(): void
    {
        $rows = $this->rows([
            $this->play('Some Single', '2026-08-10 22:30:00', release: null),
            $this->play('Another Single', '2026-08-10 22:26:00', 'CCFX', release: null),
            $this->play('Kaputt', '2026-08-10 22:22:00', 'Destroyer', 'Kaputt'),
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(ListeningLog::SONGS, $rows[0]['type']);
        $this->assertCount(3, $rows[0]['listens']);
    }

    public function test_handles_nothing_played(): void
    {
        $this->assertSame([], $this->days([]));
    }
}
