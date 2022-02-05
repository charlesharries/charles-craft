<?php

namespace helpers\models;

use craft\elements\Entry;
use helpers\spotify\Track;

class Project
{
    public static function transform(Entry $entry)
    {
        $getTrack = fn ($track) => (new Track($track['id']))->transform();

        $body = array_map(function ($block) use ($getTrack) {
            return [
                'heading' => $block->heading,
                'year' => $block->year,
                'body' => $block->body,
                'tracks' => array_map($getTrack, $block->songs),
            ];
        }, $entry->flexibleContent->all());

        return [
            'title' => $entry->title,
            'flexible_content' => $body,
        ];
    }
}
