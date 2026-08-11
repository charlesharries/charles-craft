<?php

namespace extensions;

use Craft;
use craft\web\twig\variables\CraftVariable;
use extensions\assetbundles\VideoAssetBundle;
use extensions\variables\Tracks;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init()
    {
        Craft::setAlias('@extensions', __DIR__);

        parent::init();

        if (Craft::$app->request->getIsSiteRequest()) {
            $extension = new TwigExtension();
            Craft::$app->view->registerTwigExtension($extension);

            Event::on(
                CraftVariable::class,
                CraftVariable::EVENT_INIT,
                function (Event $event) {
                    /** @var CraftVariable $variable */
                    $variable = $event->sender;
                    $variable->set('tracks', Tracks::class);
                }
            );
        }

        if (Craft::$app->request->getIsCpRequest()) {
            VideoAssetBundle::boot();
        }
    }
}
