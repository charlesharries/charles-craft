<?php

use craft\elements\Entry;
use craft\elements\Tag;
use craft\helpers\App;
use helpers\models\Book;
use helpers\models\Post;
use helpers\models\PostTag;
use helpers\models\Project;
use helpers\models\Stream;
use helpers\models\Walk;

function withTagQuery($criteria)
{
    if (!$tagParam = Craft::$app->request->getQueryParam('tags')) {
        return $criteria;
    }

    $tags = array_map(
        fn (string $tag) => Tag::findOne(['slug' => $tag]),
        explode(',', $tagParam)
    );

    return array_merge($criteria, ['relatedTo' => $tags]);
}

return [
    'endpoints' => [
        'posts.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => withTagQuery(['section' => 'posts', 'orderBy' => 'postDate desc']),
                'cache' => App::env('ENVIRONMENT') === 'dev' ? null : true,
                'transformer' => function (Entry $entry) {
                    return Post::transformForIndex($entry);
                },
            ];
        },
        'sam-posts.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'samPosts', 'orderBy' => 'postDate desc'],
                'cache' => App::env('ENVIRONMENT') === 'dev' ? null : true,
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
                'cache' => App::env('ENVIRONMENT') === 'dev' ? null : true,
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
                'cache' => App::env('ENVIRONMENT') === 'dev' ? null : true,
                'transformer' => function (Entry $entry) {
                    return Post::transform($entry);
                }
            ];
        },
        'stream.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => ['stream']],
                'cache' => App::env('ENVIRONMENT') === 'dev' ? null : true,
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
                'cache' => App::env('ENVIRONMENT') === 'dev' ? null : true,
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
                'cache' => App::env('ENVIRONMENT') === 'dev' ? null : true,
                'transformer' => function (Entry $entry) {
                    return Project::transform($entry);
                },
            ];
        },
        'walks.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'walks'],
                'cache' => App::env('ENVIRONMENT') === 'dev' ? null : true,
                'transformer' => function (Entry $entry) {
                    return Walk::transformForIndex($entry);
                },
            ];
        },
        'walks/<slug>.json' => function ($slug) {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'walks', 'slug' => $slug],
                'cache' => App::env('ENVIRONMENT') === 'dev' ? null : true,
                'one' => true,
                'transformer' => function (Entry $entry) {
                    return Walk::transform($entry);
                },
            ];
        },
        'books.json' => function () {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'books'],
                'cache' => App::env('ENVIRONMENT') === 'dev' ? null : true,
                'transformer' => function (Entry $entry) {
                    return Book::transformForIndex($entry);
                },
            ];
        },
        'books/<slug>.json' => function ($slug) {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'books', 'slug' => $slug],
                'cache' => App::env('ENVIRONMENT') === 'dev' ? null : true,
                'one' => true,
                'transformer' => function (Entry $entry) {
                    return Book::transform($entry);
                },
            ];
        },
    ]
];
