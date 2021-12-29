<?php

use craft\elements\Asset;
use craft\elements\Entry;
use mmikkel\retcon\Retcon;

trait HasImages
{
    public static function widths()
    {
        return [
            ['width' => 400],
            ['width' => 900],
            ['width' => 1500]
        ];
    }
}

class Post
{
    use HasImages;

    public static function transform(Entry $entry)
    {
        return [
            'title' => $entry->title,
            'created_at' => $entry->dateCreated,
            'body' => Retcon::$plugin->retcon->srcset($entry->body, self::widths()),
        ];
    }
}

class Stream extends Post
{
    public static function transform(Entry $entry)
    {
        $data = parent::transform($entry);
        $images = $entry->featuredImage ? $entry->featuredImage->all() : [];
        $assets = array_map(function (Asset $asset) {
            $tag = '<img src="' . $asset->getUrl() . '" alt="' . $asset->title . '" />';
            return [
                'alt' => $asset->title,
                'url' => $asset->getUrl(),
                'tag' => Retcon::$plugin->retcon->srcset($tag, self::widths()),
            ];
        }, $images);

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
