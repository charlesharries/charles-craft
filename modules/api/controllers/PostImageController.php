<?php

namespace modules\api\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class PostImageController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

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
            $wkHtmlToImage = Craft::$app->config->custom->wkhtmltoimagePath;
            $html = Craft::$app->getView()->renderTemplate($template, compact('entry'));
            $snappy = new \Knp\Snappy\Image($wkHtmlToImage);
            return $snappy->getOutputFromHtml($html);
        }, 60 * 60 * 24 * 7);

        // Set Content-Type and Cache headers
        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', 'image/jpeg');
        $headers->add('Cache-Control', 'public, max-age=604800');
        $headers->add('Expires', gmdate('D, d M Y H:i:s \G\M\T', strtotime('+1 week')));

        return $this->asRaw($output);
    }
}
