<?php

namespace extensions;

use Craft;

class Module extends \yii\base\Module
{
    public function init()
    {
        Craft::setAlias('@extensions', __DIR__);

        parent::init();

        if (Craft::$app->request->getIsSiteRequest()) {
            $extension = new TwigExtension();
            Craft::$app->view->registerTwigExtension($extension);
        }
    }
}
