<?php

namespace tests\helpers\tealfm;

use helpers\tealfm\TealFmListenRepository;
use PHPUnit\Framework\TestCase;

class TealFmListenRepositoryTest extends TestCase
{
    private function makeRow(array $overrides = []): array
    {
        return array_merge([
            'uri' => 'at://did:plc:abc123/fm.teal.alpha.feed.play/3lqk2wxyz',
            'trackName' => 'Delicious Things',
            'artistNames' => '["Wolf Alice"]',
            'releaseName' => 'Blue Weekend',
            'releaseMbId' => '8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6f',
            'playedTime' => '2026-08-10 22:30:00',
        ], $overrides);
    }

    public function test_decodes_artist_names(): void
    {
        $listen = TealFmListenRepository::hydrate($this->makeRow([
            'artistNames' => '["Wolf Alice","Julien Baker"]',
        ]));

        $this->assertSame(['Wolf Alice', 'Julien Baker'], $listen['artistNames']);
    }

    public function test_joins_artist_names_into_a_single_string(): void
    {
        $listen = TealFmListenRepository::hydrate($this->makeRow([
            'artistNames' => '["Wolf Alice","Julien Baker"]',
        ]));

        $this->assertSame('Wolf Alice, Julien Baker', $listen['artist']);
    }

    public function test_falls_back_to_no_artists_when_the_json_is_unusable(): void
    {
        $listen = TealFmListenRepository::hydrate($this->makeRow([
            'artistNames' => 'not json',
        ]));

        $this->assertSame([], $listen['artistNames']);
        $this->assertSame('', $listen['artist']);
    }

    public function test_reads_played_time_as_utc(): void
    {
        // The column carries no zone of its own, so a naive read would land in
        // the system time zone and shift the value by that offset.
        $previous = date_default_timezone_get();
        date_default_timezone_set('Australia/Sydney');

        try {
            $listen = TealFmListenRepository::hydrate($this->makeRow());
        } finally {
            date_default_timezone_set($previous);
        }

        $this->assertSame('UTC', $listen['playedTime']->getTimezone()->getName());
        $this->assertSame('2026-08-10T22:30:00+00:00', $listen['playedTime']->format(DATE_ATOM));
    }

    public function test_passes_the_remaining_columns_straight_through(): void
    {
        $listen = TealFmListenRepository::hydrate($this->makeRow());

        $this->assertSame('at://did:plc:abc123/fm.teal.alpha.feed.play/3lqk2wxyz', $listen['uri']);
        $this->assertSame('Delicious Things', $listen['trackName']);
        $this->assertSame('Blue Weekend', $listen['releaseName']);
        $this->assertSame('8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6f', $listen['releaseMbId']);
    }

    public function test_tolerates_a_listen_with_no_release(): void
    {
        $listen = TealFmListenRepository::hydrate($this->makeRow([
            'releaseName' => null,
            'releaseMbId' => null,
        ]));

        $this->assertNull($listen['releaseName']);
        $this->assertNull($listen['releaseMbId']);
    }
}
