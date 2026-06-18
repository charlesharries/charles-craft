<?php

namespace extensions\assetbundles;

use craft\ckeditor\Field as CKEditorField;
use craft\ckeditor\Plugin as CKEditorPlugin;
use craft\ckeditor\web\assets\BaseCkeditorPackageAsset;
use craft\htmlfield\events\ModifyPurifierConfigEvent;
use yii\base\Event;

class VideoAssetBundle extends BaseCkeditorPackageAsset
{
    public $sourcePath = '@webroot/js/ckeditor';

    public $js = ['video-plugin.js'];

    public array $pluginNames = ['InsertVideo'];

    public array $toolbarItems = ['insertVideo'];

    public static function boot(): void
    {
        CKEditorPlugin::registerCkeditorPackage(static::class);

        Event::on(CKEditorField::class, CKEditorField::EVENT_MODIFY_PURIFIER_CONFIG, function (ModifyPurifierConfigEvent $event) {
            $def = $event->config->getHTMLDefinition(true);
            $def->addElement('video', 'Block', 'Optional: (source, Flow) | Flow', 'Common', [
                'src' => 'URI',
                'controls' => 'Bool',
                'autoplay' => 'Bool',
                'loop' => 'Bool',
                'muted' => 'Bool',
                'poster' => 'URI',
                'playsinline' => 'Bool',
                'width' => 'Number',
                'height' => 'Number',
            ]);
            $def->addElement('source', 'Inline', 'Empty', 'Core', [
                'src' => 'URI',
                'type' => 'Text',
            ]);
        });
    }
}
