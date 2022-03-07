<?php

namespace helpers\models;

use craft\elements\Asset;
use craft\elements\Entry;
use helpers\traits\HasImages;
use mmikkel\retcon\Retcon;

class Book extends Post
{
    use HasImages;

    public static function transformForIndex(Entry $entry)
    {
        $data = parent::transformForIndex($entry);

        return array_merge($data, [
            'writer' => $entry->writer->one()->title,
            'publication_year' => $entry->publicationYear,
            'length' => $entry->length,
            'rating' => $entry->rating,
        ]);
    }

    public static function transform(Entry $entry)
    {
        $images = $entry->featuredImage ? $entry->featuredImage->all() : [];
        $assets = array_map(function (Asset $asset) {
            $tag = '<img src="' . $asset->getUrl() . '" alt="' . $asset->title . '" />';
            return [
                'alt' => $asset->title,
                'url' => $asset->getUrl('md'),
                'width' => $asset->getWidth('md'),
                'height' => $asset->getHeight('md'),
                'tag' => Retcon::$plugin->retcon->srcset($tag, self::widths()),
            ];
        }, $images);

        return [
            'title' => $entry->title,
            'created_at' => $entry->postDate->format('Y-m-d\TH:i'),
            'slug' => $entry->slug,
            'body' => Retcon::$plugin->retcon->srcset($entry->body, self::widths()),
            'rating' => $entry->rating,
            'writer' => $entry->writer->one()->title,
            'date_read' => $entry->readAt->format('Y-m-d\TH:i'),
            'length' => $entry->length,
            'publication_year' => $entry->publicationYear,
            'medium' => $entry->medium->label,
            'featured_image' => $assets,
        ];
    }
}
