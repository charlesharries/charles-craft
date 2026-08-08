<?php

namespace modules\atproto\builders;

use craft\elements\Entry;

interface RecordBuilderInterface
{
    /** The lexicon NSID this builder writes to, e.g. 'site.standard.document'. */
    public static function collection(): string;

    /** Deterministic record key for this entry, stable across calls so writes are idempotent via putRecord. */
    public static function rkeyFor(Entry $entry): string;

    /**
     * Builds the record payload for this entry, or null to skip syncing it
     * (missing required data, prerequisites not configured, etc).
     *
     * @return array<string, mixed>|null
     */
    public function build(Entry $entry): ?array;
}
