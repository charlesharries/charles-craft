<?php

namespace modules\api\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class PostImageController extends Controller
{
    public function actionGet(string $slug): Response
    {
        $entry = Entry::find()
            ->slug($slug)
            ->one();

        if (!$entry) {
            throw new NotFoundHttpException;
        }

        $output = Craft::$app->cache->getOrSet(['postimage', $slug], function () use ($entry) {
            $html = Craft::$app->getView()->renderTemplate("api/postimage.twig", compact('entry'));
            $snappy = new \Knp\Snappy\Image('/usr/local/bin/wkhtmltoimage');
            return $snappy->getOutputFromHtml($html);
        }, 5);

        // Set Content-Type and Cache headers
        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', 'image/jpeg');
        // TODO: Set headers for caching.

        return $this->asRaw($output);
    }
}
