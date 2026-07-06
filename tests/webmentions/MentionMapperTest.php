<?php

namespace tests\webmentions;

use modules\webmentions\services\MentionMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MentionMapperTest extends TestCase
{
    private function makeMention(array $overrides = []): array
    {
        return array_merge([
            'wm-id' => 12345,
            'wm-source' => 'https://example.com/post',
            'wm-target' => 'https://charlesharri.es/writing/my-post',
            'wm-property' => 'like-of',
            'published' => '2026-01-01T12:00:00+00:00',
            'author' => [
                'name' => 'Jane Doe',
                'url' => 'https://example.com',
                'photo' => 'https://example.com/photo.jpg',
            ],
            'content' => [
                'text' => 'Great post!',
            ],
        ], $overrides);
    }

    public function test_maps_basic_fields(): void
    {
        $result = MentionMapper::mapEntry($this->makeMention());

        $this->assertSame('12345', $result['webmentionId']);
        $this->assertSame('https://example.com/post', $result['webmentionSourceUrl']);
        $this->assertSame('https://charlesharri.es/writing/my-post', $result['webmentionTargetUrl']);
        $this->assertSame('like-of', $result['webmentionType']);
        $this->assertSame('Jane Doe', $result['webmentionAuthorName']);
        $this->assertSame('https://example.com', $result['webmentionAuthorUrl']);
        $this->assertSame('https://example.com/photo.jpg', $result['webmentionAuthorPhoto']);
        $this->assertSame('Great post!', $result['webmentionContent']);
        $this->assertSame('2026-01-01T12:00:00+00:00', $result['webmentionPublishedAt']);
    }

    public function test_falls_back_to_wm_received_when_no_published_date(): void
    {
        $mention = $this->makeMention(['published' => null, 'wm-received' => '2026-02-02T00:00:00+00:00']);
        unset($mention['published']);

        $result = MentionMapper::mapEntry($mention);

        $this->assertSame('2026-02-02T00:00:00+00:00', $result['webmentionPublishedAt']);
    }

    public function test_strips_html_when_only_html_content_present(): void
    {
        $mention = $this->makeMention(['content' => ['html' => '<p>Hello <b>world</b></p>']]);

        $result = MentionMapper::mapEntry($mention);

        $this->assertSame('Hello world', $result['webmentionContent']);
    }

    public function test_defaults_to_mention_of_when_property_missing(): void
    {
        $mention = $this->makeMention();
        unset($mention['wm-property']);

        $result = MentionMapper::mapEntry($mention);

        $this->assertSame('mention-of', $result['webmentionType']);
    }

    #[DataProvider('titleProvider')]
    public function test_generates_title(string $type, string $expected): void
    {
        $mapped = MentionMapper::mapEntry($this->makeMention(['wm-property' => $type]));

        $this->assertSame($expected, MentionMapper::title($mapped));
    }

    public static function titleProvider(): array
    {
        return [
            ['in-reply-to', 'Reply from Jane Doe'],
            ['like-of', 'Like from Jane Doe'],
            ['repost-of', 'Repost from Jane Doe'],
            ['bookmark-of', 'Bookmark from Jane Doe'],
            ['mention-of', 'Mention from Jane Doe'],
            ['rsvp', 'RSVP from Jane Doe'],
        ];
    }

    public function test_title_falls_back_to_unknown_author(): void
    {
        $mapped = MentionMapper::mapEntry($this->makeMention(['author' => []]));

        $this->assertSame('Like from Unknown', MentionMapper::title($mapped));
    }
}
