<?php

namespace tests\standardsite;

use modules\standardsite\services\BlueskyPostBuilder;
use PHPUnit\Framework\TestCase;

class BlueskyPostBuilderTest extends TestCase
{
    private const URL = 'https://charlesharri.es/writing/a-post';

    public function test_appends_the_url_after_the_title(): void
    {
        $result = BlueskyPostBuilder::buildText('A Post', self::URL);

        $this->assertSame("A Post\n\n" . self::URL, $result['text']);
    }

    public function test_facet_byte_range_covers_exactly_the_url(): void
    {
        $result = BlueskyPostBuilder::buildText('A Post', self::URL);
        $index = $result['facets'][0]['index'];

        $this->assertSame(
            self::URL,
            substr($result['text'], $index['byteStart'], $index['byteEnd'] - $index['byteStart'])
        );
        $this->assertSame(8, $index['byteStart']);
    }

    public function test_facet_carries_the_link_feature(): void
    {
        $result = BlueskyPostBuilder::buildText('A Post', self::URL);

        $this->assertSame([
            [
                '$type' => 'app.bsky.richtext.facet#link',
                'uri' => self::URL,
            ],
        ], $result['facets'][0]['features']);
    }

    public function test_facet_byte_range_accounts_for_multibyte_titles(): void
    {
        $result = BlueskyPostBuilder::buildText('Naïve café 🎉 emoji', self::URL);
        $index = $result['facets'][0]['index'];

        $this->assertSame(
            self::URL,
            substr($result['text'], $index['byteStart'], $index['byteEnd'] - $index['byteStart'])
        );
        // Byte offsets, not grapheme offsets: the title is 18 graphemes but 23 bytes,
        // plus the two-byte separator.
        $this->assertSame(25, $index['byteStart']);
    }

    public function test_truncates_long_titles_to_the_grapheme_limit(): void
    {
        $result = BlueskyPostBuilder::buildText(str_repeat('a', 400), self::URL);

        $this->assertSame(300, grapheme_strlen($result['text']));
        $this->assertStringEndsWith(self::URL, $result['text']);
        $this->assertStringContainsString('…', $result['text']);
    }

    public function test_truncation_leaves_the_url_intact(): void
    {
        $result = BlueskyPostBuilder::buildText(str_repeat('🎉', 400), self::URL);
        $index = $result['facets'][0]['index'];

        $this->assertSame(
            self::URL,
            substr($result['text'], $index['byteStart'], $index['byteEnd'] - $index['byteStart'])
        );
        $this->assertSame(300, grapheme_strlen($result['text']));
    }

    public function test_collapses_whitespace_in_the_title(): void
    {
        $result = BlueskyPostBuilder::buildText("A   Post\nWith  Breaks", self::URL);

        $this->assertStringStartsWith("A Post With Breaks\n\n", $result['text']);
    }

    public function test_builds_an_external_embed_record(): void
    {
        $text = BlueskyPostBuilder::buildText('A Post', self::URL);
        $record = BlueskyPostBuilder::buildRecord($text, self::URL, 'A Post', 'A summary.');

        $this->assertSame('app.bsky.feed.post', $record['$type']);
        $this->assertSame($text['text'], $record['text']);
        $this->assertSame($text['facets'], $record['facets']);
        $this->assertSame(['en'], $record['langs']);
        $this->assertSame('app.bsky.embed.external', $record['embed']['$type']);
        $this->assertSame([
            'uri' => self::URL,
            'title' => 'A Post',
            'description' => 'A summary.',
        ], $record['embed']['external']);
    }

    public function test_record_omits_thumb_when_there_is_no_blob(): void
    {
        $record = BlueskyPostBuilder::buildRecord(
            BlueskyPostBuilder::buildText('A Post', self::URL),
            self::URL,
            'A Post',
            'A summary.'
        );

        $this->assertArrayNotHasKey('thumb', $record['embed']['external']);
    }

    public function test_record_includes_thumb_when_a_blob_is_given(): void
    {
        $blob = [
            '$type' => 'blob',
            'ref' => ['$link' => 'bafkreiabc'],
            'mimeType' => 'image/jpeg',
            'size' => 12345,
        ];

        $record = BlueskyPostBuilder::buildRecord(
            BlueskyPostBuilder::buildText('A Post', self::URL),
            self::URL,
            'A Post',
            'A summary.',
            $blob
        );

        $this->assertSame($blob, $record['embed']['external']['thumb']);
    }

    public function test_record_description_defaults_to_an_empty_string(): void
    {
        $record = BlueskyPostBuilder::buildRecord(
            BlueskyPostBuilder::buildText('A Post', self::URL),
            self::URL,
            'A Post',
            null
        );

        $this->assertSame('', $record['embed']['external']['description']);
    }
}
