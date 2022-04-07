<?php

namespace helpers\models;

use craft\elements\Entry;
use helpers\spotify\Track;
use helpers\traits\HasImages;
use mmikkel\retcon\Retcon;

class Project
{
    use HasImages;

    public static function transformForIndex(Entry $entry)
    {
        return [
            'title' => $entry->title,
            'created_at' => $entry->createdAt,
            'summary' => $entry->summary,
            'external_url' => $entry->externalURL,
            'featured_image' => $entry->featuredImage->one()->getUrl(),
        ];
    }

    public static function transform(Entry $entry)
    {
        $getTrack = fn ($track) => (new Track($track['id']))->transform();

        $body = array_map(function ($block) use ($getTrack) {
            $image = null;
            if ($asset = $block->image->one()) {
                $image = '<img src="' . $asset->getUrl() . '" alt="' . $asset->title . '" />';
            }

            return [
                'heading' => $block->heading,
                'year' => $block->year,
                'body' => $block->body,
                'tracks' => array_map($getTrack, $block->songs),
                'image' => $image ? Retcon::$plugin->retcon->srcset($image, self::widths()) : null,
            ];
        }, $entry->flexibleContent->all());

        return [
            'title' => $entry->title,
            'created_at' => $entry->createdAt,
            'summary' => $entry->summary,
            'external_url' => $entry->externalURL,
            'featured_image' => $entry->featuredImage->one()->getUrl(),
            'flexible_content' => $body,
        ];
    }
}
