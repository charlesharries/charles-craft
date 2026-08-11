<?php

namespace tests\helpers\tealfm;

use helpers\tealfm\AlbumArtStore;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AlbumArtStoreTest extends TestCase
{
    const MBID = '8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6f';

    public function test_builds_a_key_under_the_environment_subfolder(): void
    {
        $this->assertSame(
            'dev/album-art/8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6f.jpg',
            AlbumArtStore::key('dev', self::MBID),
        );
    }

    public function test_keeps_environments_apart_in_the_one_bucket(): void
    {
        $this->assertNotSame(
            AlbumArtStore::key('dev', self::MBID),
            AlbumArtStore::key('production', self::MBID),
        );
    }

    public function test_normalizes_the_mbid_into_the_key(): void
    {
        // The listens table keeps whatever case teal.fm sent, so a key built
        // straight from a row has to end up somewhere deterministic.
        $this->assertSame(
            AlbumArtStore::key('dev', self::MBID),
            AlbumArtStore::key('dev', strtoupper(self::MBID)),
        );
    }

    public function test_tolerates_a_slash_wrapped_environment(): void
    {
        $this->assertSame(
            'dev/album-art/8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6f.jpg',
            AlbumArtStore::key('/dev/', self::MBID),
        );
    }

    public function test_builds_a_url_behind_the_s3_proxy(): void
    {
        $this->assertSame(
            '/assets/s3/dev/album-art/8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6f.jpg',
            AlbumArtStore::url('dev', self::MBID),
        );
    }

    #[DataProvider('unusableKeys')]
    public function test_refuses_to_build_a_key_it_cant_trust(string $environment, string $mbid): void
    {
        // An MBID ends up in an S3 key, so anything that isn't a UUID has to
        // fail here rather than reach the bucket as a path.
        $this->expectException(InvalidArgumentException::class);

        AlbumArtStore::key($environment, $mbid);
    }

    public static function unusableKeys(): array
    {
        return [
            'path traversal' => ['dev', '../../etc/passwd'],
            'not a uuid' => ['dev', 'not-a-uuid'],
            'empty mbid' => ['dev', ''],
            'empty environment' => ['', self::MBID],
            'slash-only environment' => ['/', self::MBID],
        ];
    }
}
