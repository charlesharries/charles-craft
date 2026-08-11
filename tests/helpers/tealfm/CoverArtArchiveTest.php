<?php

namespace tests\helpers\tealfm;

use helpers\tealfm\CoverArtArchive;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CoverArtArchiveTest extends TestCase
{
    public function test_accepts_a_valid_mbid(): void
    {
        $mbid = '8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6f';

        $this->assertSame($mbid, CoverArtArchive::normalizeMbid($mbid));
    }

    public function test_accepts_non_v4_uuids(): void
    {
        // MusicBrainz IDs don't reliably set the v4 version/variant bits.
        $mbid = '8f9d6bd4-5b1e-1f5a-0c9a-1a2b3c4d5e6f';

        $this->assertSame($mbid, CoverArtArchive::normalizeMbid($mbid));
    }

    public function test_lowercases_the_mbid(): void
    {
        $this->assertSame(
            '8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6f',
            CoverArtArchive::normalizeMbid('8F9D6BD4-5B1E-4F5A-9C9A-1A2B3C4D5E6F'),
        );
    }

    public function test_trims_surrounding_whitespace(): void
    {
        $this->assertSame(
            '8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6f',
            CoverArtArchive::normalizeMbid("  8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6f\n"),
        );
    }

    #[DataProvider('invalidMbids')]
    public function test_rejects_anything_that_isnt_a_uuid(?string $mbid): void
    {
        $this->assertNull(CoverArtArchive::normalizeMbid($mbid));
    }

    public static function invalidMbids(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'not a uuid' => ['not-a-uuid'],
            'unhyphenated' => ['8f9d6bd45b1e4f5a9c9a1a2b3c4d5e6f'],
            'too short' => ['8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6'],
            'non-hex' => ['8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5ezz'],
            'path traversal' => ['../../etc/passwd'],
            'trailing segment' => ['8f9d6bd4-5b1e-4f5a-9c9a-1a2b3c4d5e6f/front-1200'],
        ];
    }
}
