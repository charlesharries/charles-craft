<?php

namespace modules\api\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use DateTime;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class FaviconController extends Controller
{
    public $allowAnonymous = true;

    public function actionGet()
    {
        $date = (new DateTime())->format('j M Y g:00 a');

        // Cache is good for a week.
        $output = Craft::$app->cache->getOrSet(['postimage', $date], function () use ($date) {
            $wkHtmlToImage = Craft::$app->config->custom->wkhtmltoimagePath;
            $size = 180;
            $html = Craft::$app->getView()->renderTemplate("api/favicon.twig", compact('date', 'size'));
            $snappy = new \Knp\Snappy\Image($wkHtmlToImage, [
                'format' => 'png',
                'height' => $size,
                'width' => $size,
                'transparent' => true,
                'quality' => 1,
            ]);
            return $snappy->getOutputFromHtml($html);
        }, 60 * 60 * 24 * 365);

        // Set Content-Type and Cache headers
        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', 'image/png');
        // TODO: Set headers for caching.

        return $this->asRaw($output);
    }
}
