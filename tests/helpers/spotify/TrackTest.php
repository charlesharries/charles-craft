<?php

namespace tests\helpers\spotify;

use helpers\spotify\Track;
use PHPUnit\Framework\TestCase;

class TrackTest extends TestCase
{
    private function makeTrack(array $overrides = []): object
    {
        return json_decode(json_encode(array_merge([
            'id' => 'abc123',
            'name' => 'Test Track',
            'popularity' => 72,
            'preview_url' => 'https://example.com/preview.mp3',
            'track_number' => 3,
            'duration_ms' => 214000,
            'album' => [
                'id' => 'album1',
                'name' => 'Test Album',
                'release_date' => '2024-01-01',
                'images' => [],
                'artists' => [
                    ['id' => 'artist1', 'name' => 'Artist One'],
                ],
            ],
            'artists' => [
                ['id' => 'artist1', 'name' => 'Artist One'],
            ],
        ], $overrides)));
    }

    public function test_extracts_top_level_fields(): void
    {
        $result = Track::extractFields($this->makeTrack());

        $this->assertSame('abc123', $result['id']);
        $this->assertSame('Test Track', $result['name']);
        $this->assertSame(72, $result['popularity']);
        $this->assertSame('https://example.com/preview.mp3', $result['preview_url']);
        $this->assertSame(3, $result['track_number']);
        $this->assertSame(214000, $result['duration_ms']);
    }

    public function test_extracts_album_fields(): void
    {
        $result = Track::extractFields($this->makeTrack());

        $this->assertSame('album1', $result['album']['id']);
        $this->assertSame('Test Album', $result['album']['name']);
        $this->assertSame('2024-01-01', $result['album']['release_date']);
    }

    public function test_maps_track_artists(): void
    {
        $result = Track::extractFields($this->makeTrack());

        $this->assertCount(1, $result['artists']);
        $this->assertSame('artist1', $result['artists'][0]['id']);
        $this->assertSame('Artist One', $result['artists'][0]['name']);
    }

    public function test_maps_album_artists(): void
    {
        $result = Track::extractFields($this->makeTrack());

        $this->assertCount(1, $result['album']['artists']);
        $this->assertSame('artist1', $result['album']['artists'][0]['id']);
        $this->assertSame('Artist One', $result['album']['artists'][0]['name']);
    }

    public function test_handles_multiple_artists(): void
    {
        $track = $this->makeTrack([
            'artists' => [
                ['id' => 'a1', 'name' => 'First'],
                ['id' => 'a2', 'name' => 'Second'],
            ],
        ]);

        $result = Track::extractFields($track);

        $this->assertCount(2, $result['artists']);
        $this->assertSame('a2', $result['artists'][1]['id']);
    }
}
