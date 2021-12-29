<?php

use craft\elements\Entry;
use helpers\models\Post;
use helpers\models\Stream;

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
                'criteria' => ['section' => ['stream', 'books']],
                'cache' => null,
                'transformer' => function (Entry $entry) {
                    return Stream::transform($entry);
                },
            ];
        }
    ]
];
