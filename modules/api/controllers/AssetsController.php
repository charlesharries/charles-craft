<?php

namespace modules\api\controllers;

use Craft;
use craft\awss3\S3Client;
use craft\elements\Asset;
use craft\helpers\App;
use craft\web\Controller;

class AssetsController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionS3(string $rest)
    {
        $aws = new S3Client(['region' => App::env("AWS_S3_LOCATION"), 'version' => '2006-03-01']);
        $res = $aws->getObject(['Bucket' => App::env("AWS_S3_BUCKET"), 'Key' => $rest]);

        $maxAge = 60 * 60 * 24 * 365; // 1 year

        $headers = Craft::$app->response->headers;
        $headers->set('Content-Type', $res->get('ContentType'));
        $headers->set('Cache-Control', "public, max-age={$maxAge}, immutable");
        $headers->set('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $maxAge));

        // Craft's session starts before controllers run, so close() alone doesn't prevent
        // Set-Cookie from being sent. Strip it explicitly — asset responses don't need auth state.
        Craft::$app->getSession()->close();
        Craft::$app->response->cookies->removeAll();
        header_remove('Set-Cookie');

        return $this->asRaw($res->get('Body'));
    }
}
