<?php

namespace helpers\tealfm;

use DateTimeImmutable;

/**
 * Fetches new plays from the PDS and stores them locally. The same call is
 * a full backfill the first time it runs (the listens table is empty, so
 * there's no high-water mark to resume from) and an incremental sync every
 * time after that - callers don't need to know which one they're getting.
 */
class TealFmSyncService
{
    protected TealFmListenRepository $repository;

    public function __construct(protected string $identifier)
    {
        $this->repository = new TealFmListenRepository();
    }

    public function sync(?DateTimeImmutable $since = null): int
    {
        $client = new TealFmClient($this->identifier);

        // Left to itself the sync resumes from the newest record it already
        // holds. An explicit $since is a manual re-sync of a date range
        // instead, so it walks the whole collection and filters on playedTime
        // - the one case where paying for a full scan is the point.
        $plays = $client->getPlaysAfter($since ? null : $this->repository->latestUri(), maxPages: null);

        if ($since) {
            $plays = array_filter(
                $plays,
                fn ($play) => $play['playedTime'] && $play['playedTime'] >= $since,
            );
        }

        return $this->repository->upsertMany($plays);
    }
}
