<?php

use craft\elements\Entry;
use mmikkel\retcon\Retcon;

class Post
{
    public static function transform(Entry $entry)
    {
        return [
            'title' => $entry->title,
            'created_at' => $entry->dateCreated,
            'body' => Retcon::$plugin->retcon->srcset(
                $entry->body,
                [['width' => 275], ['width' => 900]],
            ),
        ];
    }
}

class Stream extends Post
{
    public static function transform(Entry $entry)
    {
        $data = parent::transform($entry);

        return array_merge($data, [
            'featured_image' => $entry->featuredImage,
        ]);
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
        'stream.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'stream'],
                'cache' => null,
                'transformer' => function (Entry $entry) {
                    return Stream::transform($entry);
                },
            ];
        }
    ]
];
