<?php

use craft\elements\Entry;
use helpers\models\Post;
use helpers\models\Project;
use helpers\models\Stream;

return [
    'endpoints' => [
        'posts.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'posts', 'orderBy' => 'postDate desc'],
                'cache' => null,
                'transformer' => function (Entry $entry) {
                    return Post::transformForIndex($entry);
                },
            ];
        },
        'sam-posts.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'samPosts', 'orderBy' => 'postDate desc'],
                'cache' => null,
                'transformer' => function (Entry $entry) {
                    return Post::transformForIndex($entry);
                },
            ];
        },
        'posts/<slug>.json' => function ($slug) {
            return [
                'elementType' => Entry::class,
                'criteria' => ['slug' => $slug],
                'one' => true,
                'transformer' => function (Entry $entry) {
                    return Post::transform($entry);
                }
            ];
        },
        'sam-posts/<slug>.json' => function ($slug) {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'samPosts', 'slug' => $slug],
                'one' => true,
                'transformer' => function (Entry $entry) {
                    return Post::transform($entry);
                }
            ];
        },
        'stream.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => ['stream']],
                'cache' => null,
                'transformer' => function (Entry $entry) {
                    return Stream::transformForIndex($entry);
                },
            ];
        },
        'stream/<slug>.json' => function ($slug) {
            return [
                'elementType' => Entry::class,
                'criteria' => ['slug' => $slug],
                'one' => true,
                'transformer' => function (Entry $entry) {
                    return Stream::transform($entry);
                },
            ];
        },
        'projects/<slug>.json' => function ($slug) {
            return [
                'elementType' => Entry::class,
                'criteria' => ['slug' => $slug],
                'one' => true,
                'cache' => null,
                'transformer' => function (Entry $entry) {
                    return Project::transform($entry);
                },
            ];
        }
    ]
];
