<?php

namespace modules\atproto\builders;

use Craft;
use craft\elements\Entry;
use craft\elements\Tag;
use modules\atproto\services\Tid;

class StandardSiteRecordBuilder implements RecordBuilderInterface
{
    private const COLLECTION = 'site.standard.document';

    public static function collection(): string
    {
        return self::COLLECTION;
    }

    public static function rkeyFor(Entry $entry): string
    {
        return Tid::forEntry($entry);
    }

    public function build(Entry $entry): ?array
    {
        $publicationUri = Craft::$app->projectConfig->get('atproto.publicationUri');
        if (!$publicationUri || !$entry->postDate) {
            return null;
        }

        $record = [
            '$type' => self::COLLECTION,
            'site' => $publicationUri,
            'title' => $entry->title,
            'publishedAt' => $entry->postDate->format('c'),
            'path' => $this->pathForEntry($entry),
        ];

        if ($entry->summary) {
            $record['description'] = $entry->summary;
        }

        $textContent = $this->plainTextContent($entry);
        if ($textContent) {
            $record['textContent'] = $textContent;
        }

        $tags = $this->tagsForEntry($entry);
        if (!empty($tags)) {
            $record['tags'] = $tags;
        }

        if ($entry->dateUpdated && $entry->dateUpdated > $entry->postDate) {
            $record['updatedAt'] = $entry->dateUpdated->format('c');
        }

        $record['createdAt'] = $entry->postDate->format('c');

        return $record;
    }

    private function pathForEntry(Entry $entry): string
    {
        $url = $entry->getUrl();
        if (!$url) {
            return '/' . $entry->section->handle . '/' . $entry->slug;
        }
        return parse_url($url, PHP_URL_PATH);
    }

    private function plainTextContent(Entry $entry): ?string
    {
        $body = $entry->body ?? null;
        if (!$body) {
            return null;
        }

        $text = strip_tags($body);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function tagsForEntry(Entry $entry): array
    {
        if (!$entry->tags) {
            return [];
        }

        return array_map(
            fn(Tag $tag) => $tag->title,
            $entry->tags->all()
        );
    }
}
