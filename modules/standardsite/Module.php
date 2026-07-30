<?php

namespace modules\standardsite;

use Craft;
use craft\elements\Entry;
use craft\events\DraftEvent;
use craft\events\ModelEvent;
use craft\helpers\Queue;
use craft\services\Drafts;
use modules\standardsite\jobs\SyndicateToBluesky;
use modules\standardsite\services\BlueskySyndicationService;
use modules\standardsite\services\StandardSiteService;
use yii\base\Event;

class Module extends \yii\base\Module
{
    /**
     * IDs of entries currently being published for the first time, captured before the
     * draft is applied (which is the only point at which that's still knowable) and
     * consumed once it has been.
     *
     * @var array<int, true>
     */
    private static array $firstPublishIds = [];

    public function init()
    {
        parent::init();

        Craft::setAlias('@modules/standardsite', __DIR__);

        if (Craft::$app instanceof \craft\console\Application) {
            $this->controllerNamespace = 'modules\\standardsite\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\standardsite\\controllers';
            Craft::$app->view->registerTwigExtension(new TwigExtension());
        }

        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                $entry = $event->sender;

                if (!self::shouldSyncEntry($entry)) {
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

        // Craft has no "entry created" event. A brand new entry starts life as an
        // unpublished draft, and publishing it strips the draft data from that same row,
        // so the save that makes it live isn't flagged as new. The draft being an
        // unpublished one is the signal that this is a first publish; editing a live
        // entry goes through a provisional draft, which has a canonical ID and so is
        // excluded.
        Event::on(
            Drafts::class,
            Drafts::EVENT_BEFORE_APPLY_DRAFT,
            function (DraftEvent $event) {
                if ($event->provisional || !$event->draft?->getIsUnpublishedDraft()) {
                    return;
                }

                $canonical = $event->canonical;

                if ($canonical instanceof Entry && $canonical->id !== null) {
                    self::$firstPublishIds[$canonical->id] = true;
                }
            }
        );

        Event::on(
            Drafts::class,
            Drafts::EVENT_AFTER_APPLY_DRAFT,
            function (DraftEvent $event) {
                $canonical = $event->canonical;

                if (!$canonical instanceof Entry || $canonical->id === null) {
                    return;
                }

                if (!isset(self::$firstPublishIds[$canonical->id])) {
                    return;
                }

                unset(self::$firstPublishIds[$canonical->id]);

                self::syndicateToBluesky($canonical);
            }
        );

        // Covers entries created without going through drafts, e.g. programmatically.
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                $entry = $event->sender;

                if (!$event->isNew || $entry->propagating) {
                    return;
                }

                if ($entry->getIsDraft() || $entry->getIsRevision()) {
                    return;
                }

                self::syndicateToBluesky($entry);
            }
        );
    }

    /**
     * Queues a Bluesky post for a newly created entry. Never lets a syndication problem
     * break the save it was triggered by.
     */
    private static function syndicateToBluesky(Entry $entry): void
    {
        try {
            $service = new BlueskySyndicationService();

            if (!$service->isEligible($entry)) {
                return;
            }

            $entryId = $entry->getCanonicalId();
            $siteId = $entry->siteId;

            if ($entryId === null || $siteId === null) {
                return;
            }

            $job = new SyndicateToBluesky([
                'entryId' => $entryId,
                'siteId' => $siteId,
            ]);

            // A future post date means the entry isn't live yet and no further save
            // event will fire when it becomes live, so hold the job until then.
            $delay = $entry->postDate ? max(0, $entry->postDate->getTimestamp() - time()) : 0;

            Queue::push($job, null, $delay);
        } catch (\Throwable $e) {
            Craft::error(
                "Failed to queue Bluesky syndication for entry {$entry->id}: {$e->getMessage()}",
                'standardsite'
            );
        }
    }

    private static function shouldSyncEntry(Entry $entry): bool
    {
        if ($entry->getIsDraft() || $entry->getIsRevision() || $entry->isProvisionalDraft) {
            return false;
        }

        if (!$entry->section || !in_array($entry->section->handle, StandardSiteService::SUPPORTED_SECTIONS)) {
            return false;
        }

        if (!$entry->enabled || !$entry->getEnabledForSite()) {
            return false;
        }

        if (!Craft::$app->projectConfig->get('standardsite.publicationUri')) {
            return false;
        }

        return (bool) getenv('BLUESKY_APP_PASSWORD');
    }
}
