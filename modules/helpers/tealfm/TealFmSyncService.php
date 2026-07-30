<?php

namespace helpers\tealfm;

use DateTimeImmutable;

/**
 * Fetches new plays from the PDS and stores them locally. The same call is
 * a full backfill the first time it runs (the listens table is empty, so
 * there's no `$since` to start from) and an incremental sync every time
 * after that - callers don't need to know which one they're getting.
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
        $since ??= $this->repository->latestPlayedTime() ?? new DateTimeImmutable('@0');

        $client = new TealFmClient($this->identifier);
        $plays = $client->getPlaysSince($since, maxPages: null);

        return $this->repository->upsertMany($plays);
    }
}
