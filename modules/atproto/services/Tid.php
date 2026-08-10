<?php

namespace modules\atproto\services;

use craft\elements\Entry;

final class Tid
{
    public static function forEntry(Entry $entry): string
    {
        $timestampMicros = $entry->postDate->getTimestamp() * 1000000;
        $clockId = $entry->getCanonicalId() % 1024;
        $value = ($timestampMicros << 10) | $clockId;

        return self::encode($value);
    }

    public static function generate(): string
    {
        $timestampMicros = (int)(microtime(true) * 1000000);
        $clockId = random_int(0, 1023);
        $value = ($timestampMicros << 10) | $clockId;

        return self::encode($value);
    }

    private static function encode(int $value): string
    {
        $charset = '234567abcdefghijklmnopqrstuvwxyz';
        $result = '';

        for ($i = 0; $i < 13; $i++) {
            $result = $charset[$value & 0x1f] . $result;
            $value >>= 5;
        }

        return $result;
    }
}
