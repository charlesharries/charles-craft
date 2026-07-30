<?php

namespace modules\api\console\controllers;

use craft\console\Controller;
use craft\helpers\App;
use DateTimeImmutable;
use helpers\tealfm\TealFmSyncService;

class TealFmController extends Controller
{
    public function options($actionID): array
    {
        return match ($actionID) {
            'sync' => ['since'],
            default => [],
        };
    }

    /**
     * Syncs listens from the PDS since the last one stored locally. Backfills
     * the full history the first time it's run against an empty table.
     */
    public function actionSync(?string $since = null): int
    {
        $identifier = App::env('BLUESKY_IDENTIFIER');

        if (!$identifier) {
            $this->stderr("No Bluesky credentials set. Set BLUESKY_IDENTIFIER.\n");
            return 1;
        }

        $this->stdout("Syncing teal.fm listens...\n");

        try {
            $sinceDate = $since ? new DateTimeImmutable($since) : null;
        } catch (\Exception $e) {
            $this->stderr("Invalid --since date: {$e->getMessage()}\n");
            return 1;
        }

        $count = (new TealFmSyncService($identifier))->sync($sinceDate);

        $this->stdout("Synced $count listen(s).\n");
        return 0;
    }
}
