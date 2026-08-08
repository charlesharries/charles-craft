<?php

namespace modules\atproto;

use Craft;
use craft\elements\Entry;
use craft\events\ModelEvent;
use modules\atproto\services\RecordSyncService;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init()
    {
        parent::init();

        Craft::setAlias('@modules/atproto', __DIR__);

        if (Craft::$app instanceof \craft\console\Application) {
            $this->controllerNamespace = 'modules\\atproto\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\atproto\\controllers';
            Craft::$app->view->registerTwigExtension(new TwigExtension());
        }

        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                RecordSyncService::sync($event->sender);
            }
        );
    }
}
