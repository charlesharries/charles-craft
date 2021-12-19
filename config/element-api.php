<?php

use craft\elements\Asset;
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
        $assets = array_map(function (Asset $asset) {
            return [
                'alt' => $asset->title,
                'url' => 'https://res.cloudinary.com/dnz9qbnn1/image/upload/w_450/' . $asset->filename,
            ];
        }, $entry->featuredImage->all());

        return array_merge($data, [
            'featured_image' => $assets,
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
