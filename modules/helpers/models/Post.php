<?php

namespace helpers\models;

use craft\elements\Entry;
use craft\elements\Tag;
use helpers\traits\HasImages;
use mmikkel\retcon\library\RetconDom;
use mmikkel\retcon\Retcon;



class Post
{
    use HasImages;

    public static function transform(Entry $entry)
    {
        $withTag = fn (Tag $tag) => self::toTag($tag);
        $withSrcset = Retcon::$plugin->retcon->srcset(
            $entry->body,
            self::widths(),
            'img:not([src$=".gif"])'
        );

        $withSyntaxHighlighting = function ($body) {
            $dom = new RetconDom($body);
            $doc = $dom->getDoc();
            $nodes = $dom->filter("pre");
            $hl = new \Highlight\Highlighter();

            foreach ($nodes as $node) {
                $language = str_replace("lang-", "", $node->getAttribute('class'));
                $highlighted = $hl->highlight($language ?: "html", $node->nodeValue);
                $fragment = $doc->createDocumentFragment();
                $fragment->appendXML($highlighted->value);
                $node->nodeValue = "";
                foreach ($fragment->childNodes as $childNode) {
                    $node->appendChild($doc->importNode($childNode->cloneNode(true), true));
                }
            }

            return $dom->getHtml();
        };

        $data = [
            'title' => $entry->title,
            'slug' => $entry->slug,
            'summary' => $entry->summary ?? null,
            'created_at' => $entry->postDate->format('Y-m-d\TH:i'),
            'body' => Retcon::$plugin->retcon->attr($withSyntaxHighlighting($withSrcset), 'figure', ['class' => 'Image']),
        ];

        if ($entry->tags) {
            $data['tags'] = array_map($withTag, $entry->tags->all());
        }

        return $data;
    }

    public static function transformForIndex(Entry $entry)
    {
        $withTag = fn (Tag $tag) => self::toTag($tag);

        $data = [
            'type' => $entry->section->handle,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'created_at' => $entry->postDate->format('Y-m-d\TH:i'),
            'summary' => $entry->summary,
        ];

        if ($entry->tags) {
            $data['tags'] = array_map($withTag, $entry->tags->all());
        }

        return $data;
    }

    public static function toTag(Tag $tag)
    {
        return ['title' => $tag->title, 'id' => $tag->id, 'slug' => $tag->slug];
    }
}
