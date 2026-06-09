<?php

namespace modules\standardsite\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class WellKnownController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionPublication(): Response
    {
        $uri = Craft::$app->projectConfig->get('standardsite.publicationUri');

        if (!$uri) {
            throw new \yii\web\NotFoundHttpException();
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'text/plain; charset=utf-8');
        $response->data = $uri;

        return $response;
    }
}
