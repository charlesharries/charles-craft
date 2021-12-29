<?php

namespace helpers\models;

use craft\elements\Entry;
use helpers\traits\HasImages;
use mmikkel\retcon\Retcon;

class Book
{
    use HasImages;

    public static function transform(Entry $entry)
    {
        return [
            'title' => $entry->title,
            'created_at' => $entry->dateCreated,
            'body' => Retcon::$plugin->retcon->srcset($entry->body, self::widths()),
            'rating' => $entry->rating,
            'writer' => $entry->writer->first()->title,
            'date_read' => $entry->readAt,
            'length' => $entry->length,
            'publication_year' => $entry->publicationYear,
            'medium' => $entry->medium->label,
        ];
    }
}
