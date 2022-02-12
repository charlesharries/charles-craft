<?php

namespace helpers\models;

use craft\elements\Tag;

class PostTag
{
    public static function transform(Tag $tag)
    {
        return [
            'title' => $tag->title,
            'slug' => $tag->slug,
        ];
    }

    public static function transformForIndex(Tag $tag)
    {
        return self::transform($tag);
    }
}
