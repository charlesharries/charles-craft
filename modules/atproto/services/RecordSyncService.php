<?php

namespace modules\atproto\services;

use Craft;
use craft\elements\Entry;
use modules\atproto\RecordBuilderMap;

final class RecordSyncService
{
    public static function isSyncableEntry(Entry $entry): bool
    {
        if ($entry->getIsDraft() || $entry->getIsRevision() || $entry->isProvisionalDraft) {
            return false;
        }

        if (!$entry->enabled || !$entry->getEnabledForSite()) {
            return false;
        }

        return (bool) getenv('BLUESKY_APP_PASSWORD');
    }

    public static function sync(Entry $entry): void
    {
        if (!self::isSyncableEntry($entry)) {
            return;
        }

        $builderClasses = RecordBuilderMap::buildersFor($entry);
        if (empty($builderClasses)) {
            return;
        }

        $client = new AtProtoClient();
        $authenticated = false;

        foreach ($builderClasses as $builderClass) {
            $builder = new $builderClass();
            $record = $builder->build($entry);
            if ($record === null) {
                continue;
            }

            if (!$authenticated) {
                $client->authenticate();
                $authenticated = true;
            }

            try {
                $client->putRecord($builderClass::collection(), $builderClass::rkeyFor($entry), $record);
            } catch (\Throwable $e) {
                Craft::error(
                    "Failed to sync {$builderClass::collection()} for entry {$entry->id}: {$e->getMessage()}",
                    'atproto'
                );
            }
        }
    }
}
