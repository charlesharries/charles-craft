<?php

namespace helpers\models;

use craft\elements\Entry;

class Project
{
    public static function transform(Entry $entry)
    {
        $body = array_map(function ($block) {
            return [
                'heading' => $block->heading,
                'year' => $block->year,
                'body' => $block->body
            ];
        }, $entry->flexibleContent->all());

        return [
            'title' => $entry->title,
            'flexible_content' => $body,
        ];
    }
}
