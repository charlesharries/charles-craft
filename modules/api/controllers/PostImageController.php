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

        // Cache is good for a week.
        $output = Craft::$app->cache->getOrSet(['postimage', $slug], function () use ($entry) {
            $wkHtmlToImage = Craft::$app->config->general->wkhtmltoimagePath;
            $html = Craft::$app->getView()->renderTemplate("api/postimage.twig", compact('entry'));
            $snappy = new \Knp\Snappy\Image($wkHtmlToImage);
            return $snappy->getOutputFromHtml($html);
        }, 2);

        // Set Content-Type and Cache headers
        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', 'image/jpeg');
        // TODO: Set headers for caching.

        return $this->asRaw($output);
    }
}
