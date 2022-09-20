<?php

namespace modules\api\controllers;

use Craft;
use craft\awss3\S3Client;
use craft\elements\Asset;
use craft\web\Controller;

class AssetsController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionS3(string $rest)
    {
        $aws = new S3Client(['region' => 'eu-west-2', 'version' => '2006-03-01']);
        $res = $aws->getObject(['Bucket' => 'charles-craft', 'Key' => $rest]);

        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', $res->get('ContentType'));

        return $this->asRaw($res->get('Body'));
    }
}
