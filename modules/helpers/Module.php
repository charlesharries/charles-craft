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
        Craft::setAlias('@helpers', __DIR__);
    }
}
