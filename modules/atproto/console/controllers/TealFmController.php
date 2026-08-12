<?php

namespace modules\atproto\console\controllers;

use craft\console\Controller;
use craft\helpers\App;
use DateTimeImmutable;
use helpers\tealfm\TealFmSyncService;

class TealFmController extends Controller
{
    /** In case you want to do a hard sync from a specific date */
    public ?string $since = null;

    public function options($actionID): array
    {
        return match ($actionID) {
            'sync' => ['since'],
            default => [],
        };
    }

    /** Syncs listens from the PDS since the last one stored locally. */
    public function actionSync(): int
    {
        $identifier = App::env('BLUESKY_IDENTIFIER');

        if (!$identifier) {
            $this->stderr("No Bluesky credentials set. Set BLUESKY_IDENTIFIER.\n");
            return 1;
        }

        $this->stdout("Syncing teal.fm listens...\n");

        try {
            $sinceDate = $this->since ? new DateTimeImmutable($this->since) : null;
        } catch (\Exception $e) {
            $this->stderr("Invalid --since date: {$e->getMessage()}\n");
            return 1;
        }

        $result = (new TealFmSyncService($identifier))->sync($sinceDate);

        $this->stdout("Synced {$result['listens']} new listen(s).\n");
        $this->stdout("Cover art: {$result['stored']} stored, {$result['missing']} with none.\n");

        // The listens are safely written either way, so a flaky archive isn't
        // worth a non-zero exit and a shouting cron job.
        if ($result['failed']) {
            $this->stderr("{$result['failed']} release(s) couldn't be resolved; they'll be retried next run.\n");
        }

        return 0;
    }
}
