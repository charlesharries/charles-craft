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
            'created_at' => $entry->postDate->format('Y-m-d\TH:i'),
            'body' => Retcon::$plugin->retcon->srcset($entry->body, self::widths()),
            'rating' => $entry->rating,
            'writer' => $entry->writer->one()->title,
            'date_read' => $entry->readAt->format('Y-m-d\TH:i'),
            'length' => $entry->length,
            'publication_year' => $entry->publicationYear,
            'medium' => $entry->medium->label,
        ];
    }
}
