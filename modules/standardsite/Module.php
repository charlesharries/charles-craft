<?php

namespace modules\standardsite;

use Craft;
use craft\elements\Entry;
use craft\events\ModelEvent;
use modules\standardsite\services\StandardSiteService;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init()
    {
        parent::init();

        Craft::setAlias('@modules/standardsite', __DIR__);

        if (Craft::$app instanceof \craft\console\Application) {
            $this->controllerNamespace = 'modules\\standardsite\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\standardsite\\controllers';
        }

        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                $entry = $event->sender;

                if (!$entry->section || !in_array($entry->section->handle, StandardSiteService::SUPPORTED_SECTIONS)) {
                    return;
                }

                if (!$entry->enabled || !$entry->getEnabledForSite()) {
                    return;
                }

                $publicationUri = Craft::$app->projectConfig->get('standardsite.publicationUri');
                if (!$publicationUri) {
                    return;
                }

                if (!getenv('BLUESKY_APP_PASSWORD')) {
                    return;
                }

                try {
                    $service = new StandardSiteService();
                    $service->authenticate();
                    $service->createOrUpdateDocument($entry);
                } catch (\Throwable $e) {
                    Craft::error(
                        "Failed to sync standard.site document for entry {$entry->id}: {$e->getMessage()}",
                        'standardsite'
                    );
                }
            }
        );
    }
}
