<?php

namespace modules\standardsite\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use modules\standardsite\services\AtProtoClient;
use modules\standardsite\services\StandardSiteService;

class StandardSiteController extends Controller
{
    public function actionSetup(): int
    {
        $this->stdout("Setting up standard.site publication record...\n");

        if (!getenv('BLUESKY_APP_PASSWORD')) {
            $this->stderr("No Bluesky credentials set. Set BLUESKY_IDENTIFIER and BLUESKY_APP_PASSWORD.\n");
            return 1;
        }

        $service = new StandardSiteService();
        $service->authenticate();
        $uri = $service->createOrUpdatePublication();

        $this->stdout("Publication record created: $uri\n");
        return 0;
    }

    public function actionSync(): int
    {
        $this->stdout("Syncing entries as standard.site documents...\n");

        if (!getenv('BLUESKY_APP_PASSWORD')) {
            $this->stderr("No Bluesky credentials set. Set BLUESKY_IDENTIFIER and BLUESKY_APP_PASSWORD.\n");
            return 1;
        }

        $publicationUri = Craft::$app->projectConfig->get('standardsite.publicationUri');
        if (!$publicationUri) {
            $this->stderr("Publication not set up yet. Run standardsite/standard-site/setup first.\n");
            return 1;
        }

        $service = new StandardSiteService();
        $service->authenticate();

        $entries = Entry::find()
            ->section(StandardSiteService::SUPPORTED_SECTIONS)
            ->status('live')
            ->all();

        $total = count($entries);
        $this->stdout("Found $total entries to sync.\n");

        $synced = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            try {
                $uri = $service->createOrUpdateDocument($entry);
                $synced++;
                $this->stdout("  [$synced/$total] {$entry->title} -> $uri\n");
            } catch (\Throwable $e) {
                $failed++;
                $this->stderr("  [FAIL] {$entry->title}: {$e->getMessage()}\n");
            }
        }

        $this->stdout("\nDone. Synced: $synced, Failed: $failed\n");
        return $failed > 0 ? 1 : 0;
    }

    public function actionCleanup(): int
    {
        $this->stdout("Deleting all site.standard.document records...\n");

        if (!getenv('BLUESKY_APP_PASSWORD')) {
            $this->stderr("No Bluesky credentials set.\n");
            return 1;
        }

        $client = new AtProtoClient();
        $client->authenticate();

        $deleted = 0;
        $cursor = null;

        do {
            $result = $client->listRecords('site.standard.document', 100, $cursor);
            $records = $result['records'] ?? [];
            $cursor = $result['cursor'] ?? null;

            foreach ($records as $record) {
                $rkey = basename($record['uri']);
                $client->deleteRecord('site.standard.document', $rkey);
                $deleted++;
                $this->stdout("  Deleted: $rkey\n");
            }
        } while ($cursor && !empty($records));

        $this->stdout("\nDeleted $deleted records.\n");
        return 0;
    }
}
