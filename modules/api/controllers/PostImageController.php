<?php

namespace modules\api\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class PostImageController extends Controller
{
    public $allowAnonymous = true;

    public function actionGet($slug = null): Response
    {
        $entry = null;

        if ($slug) {
            $entry = Entry::find()->slug($slug)->one();
        }

        $template = "api/postimage.twig";

        if (!$entry) {
            $template = "api/generic-image.twig";
        }

        // Cache is good for a week.
        $output = Craft::$app->cache->getOrSet(['postimage', $slug], function () use ($entry, $template) {
            $wkHtmlToImage = Craft::$app->config->general->wkhtmltoimagePath;
            $html = Craft::$app->getView()->renderTemplate($template, compact('entry'));
            $snappy = new \Knp\Snappy\Image($wkHtmlToImage);
            return $snappy->getOutputFromHtml($html);
        }, 0);

        // Set Content-Type and Cache headers
        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', 'image/jpeg');
        // TODO: Set headers for caching.

        return $this->asRaw($output);
    }
}
