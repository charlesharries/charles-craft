<?php

namespace helpers\models;

use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\Tag;
use helpers\traits\HasImages;
use mmikkel\retcon\Retcon;

class Post
{
    use HasImages;

    public static function transform(Entry $entry)
    {
        $withSrcset = Retcon::$plugin->retcon->srcset($entry->body, self::widths());
        return [
            'title' => $entry->title,
            'slug' => $entry->slug,
            'summary' => $entry->summary ?? null,
            'created_at' => $entry->postDate->format('Y-m-d\TH:i'),
            'body' => Retcon::$plugin->retcon->attr($withSrcset, 'figure', ['class' => 'Image']),
            'tags' => $entry->tags,
        ];
    }

    public static function transformForIndex(Entry $entry)
    {
        $withTag = fn (Tag $tag) => ['title' => $tag->title, 'slug' => $tag->slug];

        return [
            'title' => $entry->title,
            'slug' => $entry->slug,
            'created_at' => $entry->postDate->format('Y-m-d\TH:i'),
            'summary' => $entry->summary,
            'tags' => array_map($withTag, $entry->tags->all()),
        ];
    }
}
