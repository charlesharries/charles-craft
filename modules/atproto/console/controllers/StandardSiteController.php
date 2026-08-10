<?php

namespace modules\atproto\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use modules\atproto\builders\StandardSiteRecordBuilder;
use modules\atproto\RecordBuilderMap;
use modules\atproto\services\AtProtoClient;
use modules\atproto\services\StandardSiteService;

class StandardSiteController extends Controller
{
    public function actionSetup(): int
    {
        $this->stdout("Setting up standard.site publication record...\n");

        if (!getenv('BLUESKY_APP_PASSWORD')) {
            $this->stderr("No Bluesky credentials set. Set BLUESKY_IDENTIFIER and BLUESKY_APP_PASSWORD.\n");
            return 1;
        }

        $existingUri = Craft::$app->projectConfig->get('atproto.publicationUri');
        if ($existingUri) {
            $oldRkey = basename($existingUri);
            $this->stdout("Existing publication record found ($oldRkey), will update in place.\n");
        }

        $service = new StandardSiteService();
        $service->authenticate();
        $uri = $service->createOrUpdatePublication();

        $this->stdout("Publication record created: $uri\n");
        return 0;
    }

    public function actionResetPublication(): int
    {
        $this->stdout("Deleting existing publication record and creating a fresh one...\n");

        if (!getenv('BLUESKY_APP_PASSWORD')) {
            $this->stderr("No Bluesky credentials set.\n");
            return 1;
        }

        $client = new AtProtoClient();
        $client->authenticate();

        $deleted = 0;
        $cursor = null;
        do {
            $result = $client->listRecords('site.standard.publication', 100, $cursor);
            $records = $result['records'] ?? [];
            $cursor = $result['cursor'] ?? null;

            foreach ($records as $record) {
                $rkey = basename($record['uri']);
                $client->deleteRecord('site.standard.publication', $rkey);
                $deleted++;
                $this->stdout("  Deleted publication: $rkey\n");
            }
        } while ($cursor && !empty($records));

        $this->stdout("Deleted $deleted publication record(s).\n");

        Craft::$app->projectConfig->remove('atproto.publicationUri');

        $service = new StandardSiteService();
        $service->authenticate();
        $uri = $service->createOrUpdatePublication();

        $this->stdout("New publication record created: $uri\n");
        return 0;
    }

    public function actionSync(): int
    {
        $this->stdout("Syncing entries as standard.site documents...\n");

        if (!getenv('BLUESKY_APP_PASSWORD')) {
            $this->stderr("No Bluesky credentials set. Set BLUESKY_IDENTIFIER and BLUESKY_APP_PASSWORD.\n");
            return 1;
        }

        $publicationUri = Craft::$app->projectConfig->get('atproto.publicationUri');
        if (!$publicationUri) {
            $this->stderr("Publication not set up yet. Run atproto/standard-site/setup first.\n");
            return 1;
        }

        $client = new AtProtoClient();
        $client->authenticate();

        $entries = Entry::find()
            ->section(RecordBuilderMap::allSectionHandles())
            ->status('live')
            ->all();

        $total = count($entries);
        $this->stdout("Found $total entries to sync.\n");

        $synced = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            foreach (RecordBuilderMap::buildersFor($entry) as $builderClass) {
                $builder = new $builderClass();
                $record = $builder->build($entry);
                if ($record === null) {
                    continue;
                }

                try {
                    $result = $client->putRecord($builderClass::collection(), $builderClass::rkeyFor($entry), $record);
                    $synced++;
                    $this->stdout("  [$synced/$total] {$entry->title} ({$builderClass::collection()}) -> {$result['uri']}\n");
                } catch (\Throwable $e) {
                    $failed++;
                    $this->stderr("  [FAIL] {$entry->title} ({$builderClass::collection()}): {$e->getMessage()}\n");
                }
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
            $result = $client->listRecords(StandardSiteRecordBuilder::collection(), 100, $cursor);
            $records = $result['records'] ?? [];
            $cursor = $result['cursor'] ?? null;

            foreach ($records as $record) {
                $rkey = basename($record['uri']);
                $client->deleteRecord(StandardSiteRecordBuilder::collection(), $rkey);
                $deleted++;
                $this->stdout("  Deleted: $rkey\n");
            }
        } while ($cursor && !empty($records));

        $this->stdout("\nDeleted $deleted records.\n");
        return 0;
    }
}
