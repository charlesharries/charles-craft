<?php

namespace modules\api\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;

class PostsController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionIndex()
    {
        $section = Craft::$app->request->queryParams['section'] ?? null;
        $offset = Craft::$app->request->queryParams['offset'] ?? 1;

        return $this->renderTemplate("api/all-posts.twig", compact('section', 'offset'));
    }
}
