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
        return $this->renderTemplate("api/all-posts.twig");
    }
}
