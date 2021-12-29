<?php

namespace deploy;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\services\Dashboard;
use craft\web\View;
use deploy\widgets\DeployButton;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init()
    {
        Craft::setAlias('@deploy', __DIR__);

        parent::init();

        // Add the dashboard widget
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = DeployButton::class;
            }
        );

        // Register the templates
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['_deploy'] = __DIR__ . '/templates';
            }
        );
    }
}
