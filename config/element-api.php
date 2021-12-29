<?php

use craft\elements\Entry;
use helpers\models\Post;
use helpers\models\Stream;

return [
    'endpoints' => [
        'posts.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'posts', 'orderBy' => 'dateCreated desc'],
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
        'stream.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => ['stream', 'books']],
                'cache' => null,
                'transformer' => function (Entry $entry) {
                    return Stream::transform($entry);
                },
            ];
        }
    ]
];
