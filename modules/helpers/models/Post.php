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
        return [
            'title' => $entry->title,
            'created_at' => $entry->dateCreated,
            'body' => Retcon::$plugin->retcon->srcset($entry->body, self::widths()),
        ];
    }
}
