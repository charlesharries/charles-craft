<?php

use craft\elements\Entry;
use mmikkel\retcon\Retcon;

class Post
{
    public static function transform(Entry $entry)
    {
        return [
            'title' => $entry->title,
            'body' => Retcon::$plugin->retcon->srcset(
                $entry->body,
                [['width' => 275], ['width' => 900]],
            ),
        ];
    }
}

return [
    'endpoints' => [
        'posts.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'posts'],
                'cache' => null,
                'transformer' => function (Entry $entry) {
                    return Post::transform($entry);
                },
            ];
        },
    ]
];
