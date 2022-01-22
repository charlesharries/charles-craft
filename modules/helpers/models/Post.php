<?php

namespace helpers\models;

use craft\elements\Asset;
use craft\elements\Entry;
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
            'created_at' => $entry->postDate->format('Y-m-d\TH:i'),
            'body' => Retcon::$plugin->retcon->attr($withSrcset, 'figure', ['class' => 'Image']),
        ];
    }

    public static function transformForIndex(Entry $entry)
    {
        return [
            'title' => $entry->title,
            'slug' => $entry->slug,
            'created_at' => $entry->postDate->format('Y-m-d\TH:i'),
            'summary' => $entry->summary,
        ];
    }
}
