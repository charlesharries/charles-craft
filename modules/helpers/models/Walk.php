<?php

namespace helpers\models;

use craft\elements\Entry;
use craft\elements\MatrixBlock;
use craft\elements\Tag;
use helpers\traits\HasImages;
use mmikkel\retcon\Retcon;
use Illuminate\Support\Collection;

class Walk extends Post
{
    use HasImages;

    public static function transform(Entry $entry)
    {
        return [
            'title' => $entry->title,
            'created_at' => $entry->postDate->format('Y-m-d\TH:i'),
            'body' => Retcon::$plugin->retcon->srcset($entry->body, self::widths()),
            'summary' => $entry->summary,
            'mountains' => (new Collection($entry->mountains->all()))
                ->map(fn (Tag $mtn) => self::toTag($mtn)),
            'bags' => (new Collection($entry->bags->all()))
                ->map(function (Entry $bag) {
                    return [
                        'mountain' => self::toTag($bag->mountains->one()),
                        'designations' => self::toTag($bag->designations->one()),
                        'number' => $bag->number,
                    ];
                }),
            'meta' => (new Collection($entry->meta->all()))
                ->map(function (MatrixBlock $block) {
                    if ($block->type->handle === 'walk') {
                        return self::toAboutTheWalk($block);
                    }
                }),
            'designations' => (new Collection($entry->designations->all()))
                ->map(fn (Tag $des) => self::toTag($des)),
        ];
    }

    public static function toAboutTheWalk(MatrixBlock $block)
    {
        $toTiming = fn ($row) => [
            'location' => $row['location'],
            'reached_at' => $row['reached_at']
        ];

        return [
            'timings' => (new Collection($block->timings))->map($toTiming),
            'total_walking_time' => $block->totalWalkingTime,
            'strava' => $block->strava,
        ];
    }
}
