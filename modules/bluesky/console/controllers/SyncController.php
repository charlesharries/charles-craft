<?php
namespace modules\bluesky\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Queue;
use modules\bluesky\jobs\SyncBlueskyFeedJob;

class SyncController extends Controller
{
    public function actionFeed(): int
    {
        $this->stdout("Queuing Bluesky feed sync job...\n");

        // If Bluesky isn't configured, don't do anything
        if (!getenv('BLUESKY_APP_PASSWORD')) {
            $this->stdout("No Bluesky credentials set, exiting...");
            return 1;
        }
        
        // Push job to Craft's queue
        Queue::push(new SyncBlueskyFeedJob());
        
        $this->stdout("Job queued successfully.\n");
        return 0;
    }
    
    // Or run synchronously for testing
    public function actionFeedNow(): int
    {
        $this->stdout("Syncing Bluesky feed...\n");

        // If Bluesky isn't configured, don't do anything
        if (!getenv('BLUESKY_APP_PASSWORD')) {
            $this->stdout("No Bluesky credentials set, exiting...");
            return 1;
        }
        
        $job = new SyncBlueskyFeedJob();
        $job->execute(Craft::$app->getQueue());
        
        $this->stdout("Sync complete.\n");
        return 0;
    }
}