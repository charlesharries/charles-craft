<?php

namespace extensions\variables;

use Craft;
use craft\helpers\App;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use helpers\tealfm\AlbumArtStore;
use helpers\tealfm\CoverArtArchive;
use helpers\tealfm\ListeningChart;
use helpers\tealfm\ListeningLog;
use helpers\tealfm\TealFmAlbumArtRepository;
use helpers\tealfm\TealFmListenRepository;

/**
 * Exposes stored teal.fm listens to templates as `craft.tracks`. Deliberately
 * read-only - the repository's write side has no business being reachable from
 * a template.
 */
class Tracks
{
    /** How far back the listening log reaches, in days, counting today. */
    const DAYS = 5;

    /** How far back the grid above it reaches, before it's run back to a Monday. */
    const CHART_DAYS = 365;

    protected TealFmListenRepository $repository;

    protected TealFmAlbumArtRepository $artRepository;

    public function __construct()
    {
        $this->repository = new TealFmListenRepository();
        $this->artRepository = new TealFmAlbumArtRepository();
    }

    /**
     * Every listen played in [$start, $end), newest first.
     */
    public function between(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->repository->between($start, $end);
    }

    /**
     * The last $days days of listening, newest day first, with cover art
     * resolved for anything we hold it for.
     *
     * @return array<int, array{day: string, rows: array<int, array>}>
     */
    public function listening(int $days = self::DAYS): array
    {
        $zone = new DateTimeZone(Craft::$app->getTimeZone());
        [$start, $end] = $this->window($days, $zone);

        return $this->withArt(ListeningLog::days($this->between($start, $end), $zone));
    }

    /**
     * How many plays a day over the last $days days, as the geometry to draw
     * them as a grid.
     */
    public function chart(int $days = self::CHART_DAYS): array
    {
        $zone = new DateTimeZone(Craft::$app->getTimeZone());
        $end = new DateTimeImmutable('tomorrow', $zone);
        [$start, $days] = ListeningChart::window($end, $days);

        return ListeningChart::of($this->repository->playedTimesBetween($start, $end), $start, $days, $zone);
    }

    /**
     * The last $days days as the half-open range [$start, $end) to read plays
     * out of.
     *
     * Whole days, so "5 days" reads the same to a template as it does to
     * someone looking at the headings - today and the four before it.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    protected function window(int $days, DateTimeZone $zone): array
    {
        $end = new DateTimeImmutable('tomorrow', $zone);

        return [$end->modify("-$days days"), $end];
    }

    /**
     * Hangs an `art` URL - or null - on every listen in the log.
     *
     * The whole page's releases are looked up in one go: a row at a time would
     * be a query per listen.
     */
    protected function withArt(array $days): array
    {
        $stored = $this->artRepository->storedMbIds($this->releaseMbIds($days));

        return array_map(fn ($day) => [
            ...$day,
            'rows' => array_map(fn ($row) => [
                ...$row,
                'listens' => array_map(
                    fn ($listen) => [...$listen, 'art' => $this->artUrl($listen['releaseMbIds'], $stored)],
                    $row['listens'],
                ),
            ], $day['rows']),
        ], $days);
    }

    /**
     * Every release the log mentions, normalized the way the art table stores
     * them.
     *
     * @return string[]
     */
    protected function releaseMbIds(array $days): array
    {
        $mbids = [];

        foreach ($days as $day) {
            foreach ($day['rows'] as $row) {
                foreach ($row['listens'] as $listen) {
                    foreach ($listen['releaseMbIds'] as $mbid) {
                        $mbids[] = CoverArtArchive::normalizeMbid($mbid);
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($mbids)));
    }

    /**
     * The first of $mbids we actually hold art for. An album played under two
     * releases only needs one of them to have resolved.
     *
     * @param string[] $mbids
     * @param array<string, int> $stored
     */
    protected function artUrl(array $mbids, array $stored): ?string
    {
        $environment = trim((string) App::env('ENVIRONMENT'));

        if ($environment === '') {
            return null;
        }

        foreach ($mbids as $mbid) {
            $normalized = CoverArtArchive::normalizeMbid($mbid);

            if ($normalized !== null && isset($stored[$normalized])) {
                return AlbumArtStore::url($environment, $normalized);
            }
        }

        return null;
    }
}
