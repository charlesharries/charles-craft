<?php

namespace deploy\widgets;

use Craft;
use craft\base\Widget;

class DeployButton extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('app', 'Deploy frontend');
    }

    public static function icon()
    {
        return Craft::getAlias('@appicons/play.svg');
    }

    public function getTitle(): string
    {
        return Craft::t('app', 'Deploy frontend');
    }

    public function getBodyHtml()
    {
        $view = Craft::$app->getView();

        return $view->renderTemplate('_deploy/button');
    }
}
