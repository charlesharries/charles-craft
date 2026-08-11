<?php

namespace extensions\variables;

use DateTimeInterface;
use helpers\tealfm\TealFmListenRepository;

/**
 * Exposes stored teal.fm listens to templates as `craft.tracks`. Deliberately
 * read-only - the repository's write side has no business being reachable from
 * a template.
 */
class Tracks
{
    protected TealFmListenRepository $repository;

    public function __construct()
    {
        $this->repository = new TealFmListenRepository();
    }

    /**
     * Every listen played in [$start, $end), newest first.
     */
    public function between(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->repository->between($start, $end);
    }
}
