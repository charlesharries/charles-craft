<?php

namespace modules\webmentions;

use Craft;
use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\helpers\Queue;
use modules\webmentions\jobs\SendWebmention;
use modules\webmentions\services\LinkExtractor;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public const OUTBOUND_SECTION = 'posts';

    public function init()
    {
        parent::init();

        Craft::setAlias('@modules/webmentions', __DIR__);

        if (Craft::$app instanceof \craft\console\Application) {
            $this->controllerNamespace = 'modules\\webmentions\\console\\controllers';
        }

        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                $entry = $event->sender;

                if (!$entry->section || $entry->section->handle !== self::OUTBOUND_SECTION) {
                    return;
                }

                if (!$entry->enabled || !$entry->getEnabledForSite()) {
                    return;
                }

                $url = $entry->getUrl();
                if (!$url || !$entry->body) {
                    return;
                }

                $host = parse_url($url, PHP_URL_HOST);
                $links = LinkExtractor::extractOutboundLinks($entry->body, $host);
                if (empty($links)) {
                    return;
                }

                $cacheKey = 'webmentions.outbound.' . $entry->id;
                $alreadySent = Craft::$app->cache->get($cacheKey) ?: [];

                $newLinks = array_diff($links, $alreadySent);
                if (empty($newLinks)) {
                    return;
                }

                foreach ($newLinks as $target) {
                    Queue::push(new SendWebmention([
                        'source' => $url,
                        'target' => $target,
                    ]));
                }

                Craft::$app->cache->set($cacheKey, array_values(array_unique(array_merge($alreadySent, $newLinks))), 0);
            }
        );
    }
}
