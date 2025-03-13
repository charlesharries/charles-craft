<?php

namespace modules\api\controllers;

use Craft;
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
        $postsToShow = 10;
        $sections = ["stream", "posts", "books", "walks"];
        $entries = \Craft::$app->getElements()->createElementQuery('craft\\elements\\Entry')
            ->section($sections)
            ->limit($postsToShow)
            ->all();
            
        if (empty($entries)) {
            throw new NotFoundHttpException('No entries found');
        }
        
        // Get the latest post date for Last-Modified header
        $latestDate = max(array_map(function($entry) {
            return $entry->postDate->getTimestamp();
        }, $entries));
        
        // Generate ETag based on content
        $etag = md5(json_encode(array_map(function($entry) {
            return [
                'id' => $entry->id,
                'title' => $entry->title,
                'dateUpdated' => $entry->dateUpdated->getTimestamp()
            ];
        }, $entries)));
        
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
        
        $response->data = Craft::$app->getView()->renderTemplate('feed.xml.twig', [
            'entries' => $entries
        ]);
        
        return $response;
    }
    
    /**
     * Renders the summary feed with abbreviated content
     */
    public function actionSummaryFeed(): Response
    {
        $postsToShow = 10;
        $sections = ["stream", "posts", "books", "walks"];
        $entries = \Craft::$app->getElements()->createElementQuery('craft\\elements\\Entry')
            ->section($sections)
            ->limit($postsToShow)
            ->all();
            
        if (empty($entries)) {
            throw new NotFoundHttpException('No entries found');
        }
        
        // Get the latest post date for Last-Modified header
        $latestDate = max(array_map(function($entry) {
            return $entry->postDate->getTimestamp();
        }, $entries));
        
        // Generate ETag based on content
        $etag = md5(json_encode(array_map(function($entry) {
            return [
                'id' => $entry->id,
                'title' => $entry->title,
                'dateUpdated' => $entry->dateUpdated->getTimestamp()
            ];
        }, $entries)));
        
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
        
        $response->data = Craft::$app->getView()->renderTemplate('summary-feed.xml.twig', [
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
