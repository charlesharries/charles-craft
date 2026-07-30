<?php

namespace modules\standardsite\console\controllers;

use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\App;
use modules\standardsite\services\BlueskySyndicationService;

class BlueskyController extends Controller
{
    /**
     * @var int|null The ID of the entry to syndicate.
     */
    public ?int $entry = null;

    /**
     * @var bool Print the record that would be created without writing anything.
     */
    public bool $dryRun = false;

    /**
     * @return string[]
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['entry', 'dryRun']);
    }

    /**
     * Syndicates a single entry to Bluesky as an app.bsky.feed.post record.
     *
     * Creation is normally handled automatically when an entry is first published; this
     * covers the cases it can't see — an entry that was created disabled and enabled
     * later, or one that needs re-posting by hand.
     */
    public function actionSyndicate(): int
    {
        if ($this->entry === null) {
            $this->stderr("Pass the entry to syndicate, e.g. --entry=123.\n");
            return 1;
        }

        if (!App::env('BLUESKY_APP_PASSWORD')) {
            $this->stderr("No Bluesky credentials set. Set BLUESKY_IDENTIFIER and BLUESKY_APP_PASSWORD.\n");
            return 1;
        }

        $entry = Entry::find()->id($this->entry)->status(null)->one();

        if (!$entry instanceof Entry) {
            $this->stderr("No entry found with ID {$this->entry}.\n");
            return 1;
        }

        $service = new BlueskySyndicationService();

        if (!$service->isEligible($entry)) {
            $this->stderr("Entry {$this->entry} isn't eligible for syndication.\n");
            $this->stderr("It must be a live entry in one of: " . implode(', ', BlueskySyndicationService::SUPPORTED_SECTIONS) . ".\n");
            return 1;
        }

        if ($this->dryRun) {
            return $this->dryRun($service, $entry);
        }

        $uri = $service->syndicate($entry);

        if ($uri === null) {
            $this->stdout("Entry {$this->entry} has already been syndicated. Nothing to do.\n");
            return 0;
        }

        $this->stdout("Syndicated \"{$entry->title}\" -> $uri\n");
        return 0;
    }

    private function dryRun(BlueskySyndicationService $service, Entry $entry): int
    {
        $this->stdout("Dry run — nothing will be written.\n\n");

        $rkey = $service->rKeyForEntry($entry);
        $this->stdout("Record key: $rkey\n");

        if ($service->hasBeenSyndicated($entry)) {
            $this->stdout("A record already exists at this key; a real run would be a no-op.\n");
        }

        $thumbnail = $service->fetchThumbnail($entry);
        $this->stdout($thumbnail === null
            ? "Thumbnail: unavailable, the card would have no image.\n"
            : 'Thumbnail: ' . number_format(strlen($thumbnail)) . " bytes.\n");

        $record = $service->buildRecord($entry);
        $this->stdout("\n" . json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return 0;
    }
}
