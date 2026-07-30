<?php

namespace modules\standardsite\services;

class BlueskyPostBuilder
{
    public const COLLECTION = 'app.bsky.feed.post';

    /**
     * Bluesky counts post length in graphemes, not bytes or code points.
     */
    private const MAX_GRAPHEMES = 300;

    private const SEPARATOR = "\n\n";

    /**
     * Builds the post text and the link facet pointing at the entry URL.
     *
     * The title is truncated if the combined text would exceed Bluesky's limit;
     * the URL is never truncated, since a broken link is worse than a clipped title.
     *
     * @return array{text: string, facets: array<int, array<string, mixed>>}
     */
    public static function buildText(string $title, string $url): array
    {
        $title = self::collapseWhitespace($title);
        $available = self::MAX_GRAPHEMES
            - self::graphemeLength($url)
            - self::graphemeLength(self::SEPARATOR);

        if ($available < 1) {
            // Pathological case: the URL alone fills the post.
            return [
                'text' => $url,
                'facets' => [self::linkFacet(0, strlen($url), $url)],
            ];
        }

        if (self::graphemeLength($title) > $available) {
            $title = self::truncateGraphemes($title, $available);
        }

        $prefix = $title . self::SEPARATOR;
        $byteStart = strlen($prefix);

        return [
            'text' => $prefix . $url,
            'facets' => [self::linkFacet($byteStart, $byteStart + strlen($url), $url)],
        ];
    }

    /**
     * @param array{text: string, facets: array<int, array<string, mixed>>} $text
     * @param array<string, mixed>|null $thumbBlob A blob ref from com.atproto.repo.uploadBlob
     * @return array<string, mixed>
     */
    public static function buildRecord(
        array $text,
        string $url,
        string $title,
        ?string $description = null,
        ?array $thumbBlob = null,
    ): array {
        $external = [
            'uri' => $url,
            'title' => self::collapseWhitespace($title),
            'description' => self::collapseWhitespace($description ?? ''),
        ];

        if ($thumbBlob !== null) {
            $external['thumb'] = $thumbBlob;
        }

        return [
            '$type' => self::COLLECTION,
            'text' => $text['text'],
            'facets' => $text['facets'],
            'langs' => ['en'],
            'createdAt' => date('c'),
            'embed' => [
                '$type' => 'app.bsky.embed.external',
                'external' => $external,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function linkFacet(int $byteStart, int $byteEnd, string $url): array
    {
        return [
            'index' => [
                'byteStart' => $byteStart,
                'byteEnd' => $byteEnd,
            ],
            'features' => [
                [
                    '$type' => 'app.bsky.richtext.facet#link',
                    'uri' => $url,
                ],
            ],
        ];
    }

    /**
     * Trims to $length graphemes, leaving room for the ellipsis it appends.
     */
    private static function truncateGraphemes(string $text, int $length): string
    {
        if ($length < 2) {
            return (string) grapheme_substr($text, 0, $length);
        }

        $truncated = rtrim((string) grapheme_substr($text, 0, $length - 1));

        return $truncated . '…';
    }

    private static function collapseWhitespace(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function graphemeLength(string $text): int
    {
        return (int) grapheme_strlen($text);
    }
}
