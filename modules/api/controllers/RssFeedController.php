<?php

namespace modules\api\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use craft\web\Response;
use yii\web\NotFoundHttpException;

class RssFeedController extends Controller
{
    protected array|bool|int $allowAnonymous = true;
    
    /**
     * Renders the main feed with full content
     */
    public function actionFeed(): Response
    {
        return $this->getResponse("feed.xml.twig");
    }
    
    /**
     * Renders the summary feed with abbreviated content
     */
    public function actionSummaryFeed(): Response
    {
        return $this->getResponse("summary-feed.xml.twig");
    }

    private function getPosts()
    {
        $postsToShow = 10;
        $sections = ["stream", "posts", "books", "walks"];
        $entries =  Entry::find()
            ->section($sections)
            ->orderBy("postDate DESC")
            ->limit($postsToShow)
            ->all();

        if (empty($entries)) {
            throw new NotFoundHttpException("No entries found");
        }

        return $entries;
    }

    private function getLatestDate(array $entries)
    {
        return max(array_map(function($entry) {
            return $entry->postDate->getTimestamp();
        }, $entries)); 
    }

    private function getEtag(array $entries)
    {
        return md5(json_encode(array_map(function($entry) {
            return [
                'id' => $entry->id,
                'title' => $entry->title,
                'dateUpdated' => $entry->dateUpdated->getTimestamp()
            ];
        }, $entries)));
    }

    private function getResponse(string $template)
    {
        $entries = $this->getPosts();
        $latestDate = $this->getLatestDate($entries);
        $etag = $this->getEtag($entries);
        
        // Check if client has a valid cached version
        $response = Craft::$app->getResponse();
        $response->getHeaders()->set('Last-Modified', gmdate('D, d M Y H:i:s', $latestDate) . ' GMT');
        $response->getHeaders()->set('ETag', '"' . $etag . '"');
        
        // Set cache control headers
        $response->getHeaders()->set('Cache-Control', 'public, max-age=3600');
        
        // Check if we can return 304 Not Modified
        if ($this->checkNotModified($latestDate, $etag)) {
            return $response;
        }
        
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/rss+xml; charset=utf-8');
        $response->data = Craft::$app->getView()->renderTemplate($template, [
            'entries' => $entries
        ]);
        
        return $response;
    }
    
    /**
     * Check if the client's cached version is still valid
     */
    private function checkNotModified($timestamp, $etag): bool
    {
        $request = Craft::$app->getRequest();
        
        // Check If-Modified-Since header
        $ifModifiedSince = $request->getHeaders()->get('If-Modified-Since');
        if ($ifModifiedSince !== null) {
            $ifModifiedSinceTime = strtotime($ifModifiedSince);
            if ($ifModifiedSinceTime !== false && $timestamp <= $ifModifiedSinceTime) {
                Craft::$app->getResponse()->setStatusCode(304);
                return true;
            }
        }
        
        // Check If-None-Match header
        $ifNoneMatch = $request->getHeaders()->get('If-None-Match');
        if ($ifNoneMatch !== null && $ifNoneMatch === '"' . $etag . '"') {
            Craft::$app->getResponse()->setStatusCode(304);
            return true;
        }
        
        return false;
    }
}
