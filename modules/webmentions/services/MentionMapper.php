<?php

namespace modules\webmentions\services;

class MentionMapper
{
    private const TYPE_LABELS = [
        'in-reply-to' => 'Reply',
        'like-of' => 'Like',
        'repost-of' => 'Repost',
        'bookmark-of' => 'Bookmark',
        'mention-of' => 'Mention',
        'rsvp' => 'RSVP',
    ];

    /**
     * Maps a single JF2 mention item (from webmention.io's mentions.jf2 API) to Craft field values.
     */
    public static function mapEntry(array $item): array
    {
        $author = $item['author'] ?? [];
        $type = $item['wm-property'] ?? 'mention-of';

        return [
            'webmentionId' => (string)($item['wm-id'] ?? ''),
            'webmentionSourceUrl' => $item['wm-source'] ?? $item['url'] ?? '',
            'webmentionTargetUrl' => $item['wm-target'] ?? '',
            'webmentionType' => $type,
            'webmentionAuthorName' => $author['name'] ?? '',
            'webmentionAuthorUrl' => $author['url'] ?? '',
            'webmentionAuthorPhoto' => $author['photo'] ?? '',
            'webmentionContent' => self::plainTextContent($item),
            'webmentionPublishedAt' => $item['published'] ?? $item['wm-received'] ?? null,
        ];
    }

    public static function title(array $mapped): string
    {
        $label = self::TYPE_LABELS[$mapped['webmentionType']] ?? 'Mention';
        $author = $mapped['webmentionAuthorName'] ?: 'Unknown';

        return "$label from $author";
    }

    private static function plainTextContent(array $item): string
    {
        $content = $item['content']['text'] ?? null;
        if ($content !== null) {
            return trim($content);
        }

        $html = $item['content']['html'] ?? null;
        if ($html !== null) {
            return trim(strip_tags($html));
        }

        return '';
    }
}
