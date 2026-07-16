<?php

namespace modules\api\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use helpers\tealfm\TealFmStats;
use yii\web\ServerErrorHttpException;

class TealFmController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionStats()
    {
        $identifier = App::env('BLUESKY_IDENTIFIER');

        if (!$identifier) {
            throw new ServerErrorHttpException("no credentials");
        }

        $period = Craft::$app->request->getQueryParam('period', '7days');

        if (!isset(TealFmStats::PERIODS[$period])) {
            $period = '7days';
        }

        $data = Craft::$app->cache->getOrSet(
            TealFmStats::cacheKey($period),
            fn () => TealFmStats::compute($identifier, $period),
            TealFmStats::CACHE_DURATION
        );

        $headers = Craft::$app->response->headers;
        $headers->add("Cache-Control", "public, max-age=300, stale-while-revalidate=60");

        return $this->asJson($data);
    }
}
