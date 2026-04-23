<?php

namespace modules\api\controllers;

use Craft;
use craft\web\Controller;
use DateTime;
use HeadlessChromium\Communication\Message;
use yii\web\Response;

class FaviconController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionGet()
    {
        $date = (new DateTime())->format('j M Y g:00 a');

        // Cache is good for a week.
        $output = Craft::$app->cache->getOrSet(['postimage', $date], function () use ($date) {
            $chromiumPath = Craft::$app->config->custom->chromiumPath;
            $size = 180;
            $html = Craft::$app->getView()->renderTemplate("api/favicon.twig", compact('date', 'size'));

            $browserFactory = new \HeadlessChromium\BrowserFactory($chromiumPath);
            $browser = $browserFactory->createBrowser([
                'headless' => true,
                'noSandbox' => true,
                'windowSize' => [$size, $size],
            ]);

            try {
                $page = $browser->createPage();
                $page->setViewport($size, $size);
                $page->setHtml($html, 5000);
                $page->evaluate('document.fonts.ready.then(() => true)')->getReturnValue(5000);

                // Transparent background: override the default white page background.
                $page->getSession()->sendMessageSync(new Message(
                    'Emulation.setDefaultBackgroundColorOverride',
                    ['color' => ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]]
                ));

                $screenshot = $page->screenshot(['format' => 'png']);
                return $screenshot->getRawBinary();
            } finally {
                $browser->close();
            }
        }, 60 * 60 * 24 * 365);

        // Set Content-Type and Cache headers
        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', 'image/png');
        $headers->add('Cache-Control', 'public, max-age=3600');
        $headers->add('Expires', gmdate('D, d M Y H:i:s \G\M\T', strtotime('+1 hour')));

        return $this->asRaw($output);
    }
}
