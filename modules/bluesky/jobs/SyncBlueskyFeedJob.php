<?php
namespace modules\bluesky\jobs;

use craft\queue\BaseJob;
use modules\bluesky\services\BlueskyService;

class SyncBlueskyFeedJob extends BaseJob
{
    public function execute($queue): void
    {
        $blueskyService = new BlueskyService();
        
        // Authenticate
        $this->setProgress($queue, 0.1, 'Authenticating with Bluesky...');
        $blueskyService->authenticate();
        
        // Fetch posts
        $this->setProgress($queue, 0.3, 'Fetching new posts...');
        $posts = $blueskyService->fetchNewPosts();
        
        // Process and save
        $total = count($posts);
        foreach ($posts as $index => $post) {
            $progress = 0.3 + (0.7 * ($index / $total));
            $this->setProgress($queue, $progress, "Processing post {$index}/{$total}");
            
            $blueskyService->savePost($post);
        }
        
        $this->setProgress($queue, 1, 'Sync complete');
    }

    protected function defaultDescription(): ?string
    {
        return 'Syncing Bluesky feed';
    }
}