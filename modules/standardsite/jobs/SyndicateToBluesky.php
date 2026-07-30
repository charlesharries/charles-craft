<?php

namespace modules\standardsite\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use modules\standardsite\services\BlueskySyndicationService;

class SyndicateToBluesky extends BaseJob
{
    public int $entryId;
    public int $siteId;

    protected function defaultDescription(): ?string
    {
        return 'Syndicating entry to Bluesky';
    }

    public function execute($queue): void
    {
        $entry = Entry::find()
            ->id($this->entryId)
            ->siteId($this->siteId)
            ->status(null)
            ->one();

        if (!$entry instanceof Entry) {
            Craft::warning(
                "Skipping Bluesky syndication: entry {$this->entryId} no longer exists.",
                'standardsite'
            );

            return;
        }

        $service = new BlueskySyndicationService();

        // Re-checked here as well as at push time: the entry may have been disabled or
        // deleted since, which matters most for jobs delayed until a future post date.
        if (!$service->isEligible($entry)) {
            Craft::info(
                "Skipping Bluesky syndication: entry {$this->entryId} is no longer eligible.",
                'standardsite'
            );

            return;
        }

        $uri = $service->syndicate($entry);

        if ($uri === null) {
            Craft::info(
                "Entry {$this->entryId} was already syndicated to Bluesky.",
                'standardsite'
            );

            return;
        }

        Craft::info("Syndicated entry {$this->entryId} to Bluesky: $uri", 'standardsite');
    }
}
