<?php

namespace helpers\tealfm;

use Craft;
use DateTimeImmutable;
use Throwable;

/**
 * Fetches new plays from the PDS and stores them locally. The same call is
 * a full backfill the first time it runs (the listens table is empty, so
 * there's no high-water mark to resume from) and an incremental sync every
 * time after that - callers don't need to know which one they're getting.
 */
class TealFmSyncService
{
    /**
     * The archive redirects to archive.org, which throttles callers that don't
     * pace themselves. A second apart is polite and costs nothing once the
     * catalogue is resolved and there's a release or two per run.
     */
    const REQUEST_INTERVAL = 1_000_000;

    /**
     * A run of failures back to back is the archive telling us to stop, rather
     * than bad luck with individual releases.
     */
    const MAX_CONSECUTIVE_FAILURES = 3;

    protected TealFmListenRepository $repository;

    protected TealFmAlbumArtRepository $artRepository;

    public function __construct(protected string $identifier)
    {
        $this->repository = new TealFmListenRepository();
        $this->artRepository = new TealFmAlbumArtRepository();
    }

    /**
     * @return array{listens: int, stored: int, missing: int, failed: int}
     */
    public function sync(?DateTimeImmutable $since = null): array
    {
        $client = new TealFmClient($this->identifier);

        $plays = $client->getPlaysAfter($since ? [] : $this->latestUris(), maxPages: null);

        if ($since) {
            $plays = array_filter(
                $plays,
                fn ($play) => $play['playedTime'] && $play['playedTime'] >= $since,
            );
        }

        $listens = $this->repository->upsertMany($plays);


        $art = $this->storeArt();

        return [
            'listens' => $listens,
            'stored' => $art['stored'],
            'missing' => $art['missing'],
            'failed' => $art['failed'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    protected function latestUris(): array
    {
        $uris = [];

        foreach (TealFmClient::COLLECTIONS as $collection) {
            $uris[$collection] = $this->repository->latestUri($collection);
        }

        return $uris;
    }

    /**
     * Resolves cover art for every release we haven't resolved yet, storing it
     * in S3 so nothing has to ask the archive at render time.
     *
     * Driven by the table rather than the batch just synced, which means the
     * first run after this shipped backfills the whole play history, and a run
     * that pulls down nothing new still clears anything left outstanding.
     *
     * @return array{stored: int, missing: int, failed: int}
     */
    protected function storeArt(): array
    {
        $counts = ['stored' => 0, 'missing' => 0, 'failed' => 0];

        if (!AlbumArtStore::isConfigured()) {
            Craft::warning('Skipping cover art: no S3 configuration.', __METHOD__);

            return $counts;
        }

        $store = new AlbumArtStore();
        $archive = new CoverArtArchive();
        $consecutiveFailures = 0;

        foreach ($this->artRepository->unresolvedMbIds() as $i => $mbid) {
            // Skipped before the first request so a normal sync of a release or
            // two doesn't sit around waiting on nothing.
            if ($i > 0) {
                usleep(self::REQUEST_INTERVAL);
            }

            try {
                $art = $archive->fetch($mbid);

                if ($art === null) {
                    $this->artRepository->record($mbid, TealFmAlbumArtRepository::STATUS_MISSING);
                    $counts['missing']++;
                } else {
                    // The object goes up before the row that claims it's there.
                    // A crash in between leaves an orphan the next run
                    // overwrites; the other order leaves a row pointing at
                    // nothing, and nothing that would ever notice.
                    $store->put($mbid, $art['body'], $art['contentType']);
                    $this->artRepository->record($mbid, TealFmAlbumArtRepository::STATUS_STORED);
                    $counts['stored']++;
                }

                $consecutiveFailures = 0;
            } catch (Throwable $e) {
                // Nothing is recorded, so the release comes back round on the
                // next run. Recording it as a miss here would mark it art-less
                // for good over what's usually a transient failure.
                $counts['failed']++;
                Craft::error("Cover art for $mbid: {$e->getMessage()}", __METHOD__);

                if (++$consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                    Craft::error('Too many cover art failures in a row; stopping.', __METHOD__);
                    break;
                }
            }
        }

        return $counts;
    }
}
