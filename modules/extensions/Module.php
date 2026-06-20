<?php

namespace extensions;

use Craft;
use extensions\assetbundles\VideoAssetBundle;

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

        if (Craft::$app->request->getIsCpRequest()) {
            VideoAssetBundle::boot();
        }
    }
}
