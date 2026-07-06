<?php

namespace modules\webmentions\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\App;
use modules\webmentions\services\MentionMapper;
use modules\webmentions\services\WebmentionIoClient;

class PullController extends Controller
{
    private const SECTION = 'webmentions';
    private const TARGET_SECTIONS = ['posts', 'stream', 'books', 'walks'];

    public function actionIndex(): int
    {
        $token = App::env('WEBMENTION_IO_TOKEN');
        if (!$token) {
            $this->stderr("No webmention.io token set. Set WEBMENTION_IO_TOKEN.\n");
            return 1;
        }

        $sinceId = Craft::$app->projectConfig->get('webmentions.lastId');

        $client = new WebmentionIoClient($token);
        $mentions = $client->fetchMentions($sinceId);

        $this->stdout('Found ' . count($mentions) . " mention(s) to process.\n");

        $created = 0;
        $updated = 0;
        $maxId = $sinceId;

        foreach ($mentions as $item) {
            $mapped = MentionMapper::mapEntry($item);
            if (!$mapped['webmentionId']) {
                continue;
            }

            $entry = Entry::find()
                ->section(self::SECTION)
                ->status(null)
                ->webmentionId($mapped['webmentionId'])
                ->one();

            $isNew = !$entry;
            if ($isNew) {
                $entry = new Entry();
                $entry->sectionId = Craft::$app->entries->getSectionByHandle(self::SECTION)->id;
                $entry->enabled = false;
            }

            foreach ($mapped as $handle => $value) {
                $entry->setFieldValue($handle, $value);
            }
            $entry->title = MentionMapper::title($mapped);
            $entry->setFieldValue('webmentionTargetEntry', $this->resolveTargetEntryId($mapped['webmentionTargetUrl']));

            if (!Craft::$app->elements->saveElement($entry)) {
                $this->stderr("Failed to save webmention {$mapped['webmentionId']}: " . implode(', ', $entry->getErrorSummary(true)) . "\n");
                continue;
            }

            $isNew ? $created++ : $updated++;
            $this->stdout("  [{$mapped['webmentionId']}] {$entry->title}\n");

            if (!$maxId || (int)$mapped['webmentionId'] > (int)$maxId) {
                $maxId = $mapped['webmentionId'];
            }
        }

        if ($maxId && $maxId !== $sinceId) {
            Craft::$app->projectConfig->set('webmentions.lastId', $maxId);
        }

        $this->stdout("Done. Created: $created, Updated: $updated\n");
        return 0;
    }

    public function actionStatus(): int
    {
        $sinceId = Craft::$app->projectConfig->get('webmentions.lastId');
        $token = App::env('WEBMENTION_IO_TOKEN');

        $this->stdout('Token set: ' . ($token ? 'yes' : 'no') . "\n");
        $this->stdout('Last seen wm-id: ' . ($sinceId ?: 'none') . "\n");

        return 0;
    }

    private function resolveTargetEntryId(string $targetUrl): ?int
    {
        if (!$targetUrl) {
            return null;
        }

        $path = trim((string)parse_url($targetUrl, PHP_URL_PATH), '/');
        $siteId = Craft::$app->sites->getPrimarySite()->id;

        $element = Craft::$app->elements->getElementByUri($path, $siteId, true);
        if ($element instanceof Entry && $element->section && in_array($element->section->handle, self::TARGET_SECTIONS, true)) {
            return $element->id;
        }

        return null;
    }
}
