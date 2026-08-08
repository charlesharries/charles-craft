<?php

namespace modules\atproto;

use craft\elements\Entry;
use modules\atproto\builders\RecordBuilderInterface;
use modules\atproto\builders\StandardSiteRecordBuilder;

final class RecordBuilderMap
{
    /**
     * @return array<int, array{from: string[], to: class-string<RecordBuilderInterface>}>
     */
    private static function rules(): array
    {
        return [
            [
                'from' => ['posts', 'stream', 'books', 'walks'],
                'to' => StandardSiteRecordBuilder::class,
            ],
        ];
    }

    /** @return class-string<RecordBuilderInterface>[] */
    public static function buildersFor(Entry $entry): array
    {
        $handle = $entry->section->handle ?? null;
        if ($handle === null) {
            return [];
        }

        $matches = [];
        foreach (self::rules() as $rule) {
            if (in_array($handle, $rule['from'], true)) {
                $matches[] = $rule['to'];
            }
        }

        return $matches;
    }

    /** Union of every section handle across all rules, for entry queries. */
    public static function allSectionHandles(): array
    {
        $handles = [];
        foreach (self::rules() as $rule) {
            $handles = array_merge($handles, $rule['from']);
        }

        return array_values(array_unique($handles));
    }
}
