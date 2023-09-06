<?php

namespace modules\api;

use Craft;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;
use modules\api\jobs\NotifyUmami;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init()
    {
        Craft::setAlias('@api', __DIR__);

        parent::init();

        // Set the controllerNamespace
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'api\console\controllers';
        }

        // Base template directory
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $e) {
                if (is_dir($baseDir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates')) {
                    $e->roots[$this->id] = $baseDir;
                }
            }
        );
    }
}
