<?php

namespace helpers\models;

use craft\elements\Entry;
use helpers\spotify\Track;
use helpers\traits\HasImages;
use mmikkel\retcon\Retcon;

class Project
{
    use HasImages;

    public static function transform(Entry $entry)
    {
        $getTrack = fn ($track) => (new Track($track['id']))->transform();

        $body = array_map(function ($block) use ($getTrack) {
            $image = '<img src="' . $block->image->one()->getUrl() . '" alt="' . $block->image->one()->title . '" />';

            return [
                'heading' => $block->heading,
                'year' => $block->year,
                'body' => $block->body,
                'tracks' => array_map($getTrack, $block->songs),
                'image' => Retcon::$plugin->retcon->srcset($image, self::widths()),
            ];
        }, $entry->flexibleContent->all());

        return [
            'title' => $entry->title,
            'flexible_content' => $body,
        ];
    }
}
