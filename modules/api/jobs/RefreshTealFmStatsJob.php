<?php

namespace modules\api\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use helpers\tealfm\TealFmStats;

/**
 * Self-requeuing job: refreshes the teal.fm stats cache, then pushes a
 * delayed copy of itself so it keeps running for as long as the site's
 * `queue/run` cron keeps firing. The refresh is wrapped in try/finally so a
 * transient failure (eg. the PDS is briefly unreachable) doesn't break the
 * chain - it just tries again next cycle.
 */
class RefreshTealFmStatsJob extends BaseJob
{
    const REQUEUE_DELAY = 280;

    public function execute($queue): void
    {
        try {
            $identifier = App::env('BLUESKY_IDENTIFIER');

            if ($identifier) {
                foreach (array_keys(TealFmStats::PERIODS) as $period) {
                    $stats = TealFmStats::compute($identifier, $period);
                    Craft::$app->cache->set(TealFmStats::cacheKey($period), $stats, TealFmStats::CACHE_DURATION);
                }
            }
        } finally {
            Craft::$app->queue->delay(self::REQUEUE_DELAY)->push(new self());
        }
    }

    protected function defaultDescription(): ?string
    {
        return 'Refreshing teal.fm stats';
    }
}
