<?php

namespace modules\api\controllers;

use Aws\AwsClient;
use Craft;
use craft\awss3\S3Client;
use craft\helpers\App;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class AssetsController extends Controller
{
    public function actionS3(string $rest)
    {
        $aws = new S3Client(['region' => 'eu-west-2', 'version' => '2006-03-01']);
        $res = $aws->getObject(['Bucket' => 'charles-craft', 'Key' => $rest]);

        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', $res->get('ContentType'));

        return $this->asRaw($res->get('Body'));
    }
}
