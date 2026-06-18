<?php
namespace modules\bluesky;

use Craft;

class Module extends \yii\base\Module
{
    public function init()
    {
        parent::init();
        
        // Set alias for the module
        Craft::setAlias('@modules/bluesky', __DIR__);
        
        // Register console commands
        if (Craft::$app instanceof \craft\console\Application) {
            $this->controllerNamespace = 'modules\\bluesky\\console\\controllers';
        }
    }
}