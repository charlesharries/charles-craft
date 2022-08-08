<?php

namespace modules\api\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class LatestTracksController extends Controller
{
    public $allowAnonymous = true;

    private $baseURL = "http://ws.audioscrobbler.com/2.0/";

    public function actionGet()
    {
        $user = App::env('LAST_FM_USER');
        $apiKey = App::env('LAST_FM_API_KEY');

        if (!($user && $apiKey)) {
            throw new \yii\web\ServerErrorHttpException("no credentials");
        }

        $client = new \GuzzleHttp\Client();
        $response = $client->request("GET", $this->baseURL, [
            "query" => [
                "method" => "user.getrecenttracks",
                "user" => $user,
                "api_key" => $apiKey,
                "format" => "json",
                "limit" => 5,
            ]
        ]);

        if (!$response->getStatusCode() > 399) {
            throw new \yii\web\ServerErrorHttpException("error from lastfm");
        }

        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', 'application/json');
        $headers->add("Cache-Control", "public, max-age=60, stale-while-revalidate=30");

        return $this->asRaw($response->getBody());
    }
}
