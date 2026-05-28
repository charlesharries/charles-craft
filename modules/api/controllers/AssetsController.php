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

        // Close the session before responding so Craft doesn't emit a Set-Cookie header,
        // which would cause Cloudflare to bypass its cache for this response.
        Craft::$app->getSession()->close();

        $maxAge = 60 * 60 * 24 * 365; // 1 year

        $headers = Craft::$app->response->headers;
        $headers->set('Content-Type', $res->get('ContentType'));
        $headers->set('Cache-Control', "public, max-age={$maxAge}, immutable");
        $headers->set('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $maxAge));

        return $this->asRaw($res->get('Body'));
    }
}
