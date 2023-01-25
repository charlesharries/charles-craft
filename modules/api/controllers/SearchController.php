<?php

namespace modules\api\controllers;

use Craft;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\web\Controller;
use DateTime;
use Illuminate\Support\Collection;
use mmikkel\retcon\library\RetconDom;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SearchResult
{
    public string $title;
    public string $postDate;
    public string $url;
    public string $summary;
    public string $result;
    private string $searchTerm;
    public Collection $tags;

    public function __construct(Entry $entry, string $searchTerm)
    {
        $this->title = $entry->title;
        $this->postDate = $entry->postDate->format("l, j M Y g:i a");
        $this->url = $entry->url;
        $this->summary = $entry->summary ?? "";
        $this->searchTerm = $searchTerm;
        $this->tags = $this->getTags($entry);
        $this->result = $this->highlight($entry->body);
    }

    private function highlight(?string $body)
    {
        if (!$body) return "";

        $found = "";
        $match = "";
        $dom = new RetconDom($body);
        $elements = $dom->filter('p, blockquote, ul, ol, figcaption');
        foreach ($elements as $element) {
            if (\str_contains(strtolower($element->textContent), strtolower($this->searchTerm))) {
                $match = \substr(\stristr($element->textContent, $this->searchTerm), 0, strlen($this->searchTerm));
                $found = $element->textContent;
                break;
            }
        }

        if ($found === "") {
            return $this->summary;
        }

        return str_replace(
            $match,
            '<mark>' . $match . '</mark>',
            $found
        );
    }

    private function getTags(Entry $entry)
    {
        if (!$entry->tags) return new Collection();

        return (new Collection($entry->tags->all()))->map(function (Tag $tag) {
            return $tag->title;
        });
    }
}

class SearchController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionGet($q): Response
    {
        $entries = new Collection(Entry::find()
            ->section("not projects")
            ->search('"' . $q . '"')
            ->all());

        return $this->asJson($entries->map(function (Entry $entry) use ($q) {
            return new SearchResult($entry, $q);
        }));
    }
}
