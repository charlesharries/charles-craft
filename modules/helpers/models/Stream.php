<?php

namespace helpers\models;

use craft\elements\Entry;
use craft\elements\Asset;
use mmikkel\retcon\Retcon;

class Stream extends Post
{
    public static function transform(Entry $entry)
    {
        if ($entry->section->handle === 'books') {
            return Book::transform($entry);
        }

        $data = parent::transform($entry);
        $images = $entry->featuredImage ? $entry->featuredImage->all() : [];
        $assets = array_map(function (Asset $asset) {
            $tag = '<img src="' . $asset->getUrl() . '" alt="' . $asset->title . '" />';
            return [
                'alt' => $asset->title,
                'url' => $asset->getUrl('md'),
                'tag' => Retcon::$plugin->retcon->srcset($tag, self::widths()),
            ];
        }, $images);

        return array_merge($data, [
            'featured_image' => $assets,
        ]);
    }
}
