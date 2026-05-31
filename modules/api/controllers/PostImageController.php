<?php

namespace modules\api\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\Response;

class PostImageController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionGet($slug = null): Response
    {
        return $this->renderImage($slug, 'v1');
    }

    public function actionGetV2($slug = null): Response
    {
        return $this->renderImage($slug, 'v2');
    }

    private function renderImage(?string $slug, string $version): Response
    {
        $entry = null;

        if ($slug) {
            $entry = Entry::find()->slug($slug)->one();
        }

        $templates = [
            'v1' => ['post' => 'api/postimage.twig', 'generic' => 'api/generic-image.twig'],
            'v2' => ['post' => 'api/postimage-v2.twig', 'generic' => 'api/generic-image-v2.twig'],
        ];

        $template = $entry ? $templates[$version]['post'] : $templates[$version]['generic'];

        $cacheTime = (int) (getenv('POST_IMAGE_CACHE_TTL') ?: 60 * 60 * 24 * 7);

        $output = Craft::$app->cache->getOrSet(['pp', $version, $slug], function () use ($entry, $template) {
            $chromiumPath = Craft::$app->config->custom->chromiumPath;
            $html = Craft::$app->getView()->renderTemplate($template, compact('entry'));

            // Otherwise Chrome will try to write logs to www-data's home directory
            putenv('HOME=/tmp/chrome-home');

            $browserFactory = new \HeadlessChromium\BrowserFactory($chromiumPath);
            $browser = $browserFactory->createBrowser([
                'headless' => true,
                'noSandbox' => true,
                'windowSize' => [1200, 630],
                'customFlags' => [
                    '--disable-dev-shm-usage',
                    '--disable-crash-reporter',
                    '--crash-dumps-dir=/tmp/chrome-crashes'
                ],
            ]);

            try {
                $page = $browser->createPage();
                $page->setViewport(1200, 630);
                $page->setHtml($html, 5000);
                $page->evaluate('document.fonts.ready.then(() => true)')->getReturnValue(5000);
                $screenshot = $page->screenshot([
                    'format' => 'jpeg',
                    'quality' => 90,
                ]);
                return $screenshot->getRawBinary();
            } finally {
                $browser->close();
            }
        }, $cacheTime);

        // Set Content-Type and Cache headers
        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', 'image/jpeg');
        $headers->add('Cache-Control', 'public, max-age=604800');
        $headers->add('Expires', gmdate('D, d M Y H:i:s \G\M\T', strtotime('+1 week')));

        return $this->asRaw($output);
    }
}
