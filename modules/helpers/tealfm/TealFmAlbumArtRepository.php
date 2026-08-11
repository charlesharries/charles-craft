<?php

namespace helpers\tealfm;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateTimeImmutable;

/**
 * Tracks which releases we've resolved cover art for. A row here - of either
 * status - means the Cover Art Archive has been asked and answered, so the sync
 * never asks twice.
 */
class TealFmAlbumArtRepository
{
    const TABLE = '{{%tealfm_album_art}}';

    const STATUS_STORED = 'stored';

    const STATUS_MISSING = 'missing';

    /**
     * Every release we hold a listen for but haven't resolved art for yet - the
     * sync's work list. Because a miss is recorded as firmly as a hit, releases
     * the archive has nothing for drop out for good instead of being re-asked
     * about on every run.
     *
     * The two sets are diffed in PHP rather than SQL because the columns don't
     * agree on case: listens keep whatever teal.fm sent, while art rows are
     * always normalized. Leaving that to the database would work fine on
     * MySQL's case-insensitive collation and quietly break anywhere stricter.
     *
     * @return string[]
     */
    public function unresolvedMbIds(): array
    {
        $played = (new Query())
            ->select(['releaseMbId', 'playedTime'])
            ->distinct()
            ->from(TealFmListenRepository::TABLE)
            ->where(['not', ['releaseMbId' => null]])
            ->orderBy(['playedTime' => SORT_DESC])
            ->column();

        $played = array_unique(array_filter(array_map(
            fn ($mbid) => CoverArtArchive::normalizeMbid($mbid),
            $played,
        )));

        $resolved = array_flip((new Query())
            ->select(['releaseMbId'])
            ->from(self::TABLE)
            ->column());

        return array_values(array_filter(
            $played,
            fn ($mbid) => !isset($resolved[$mbid]),
        ));
    }

    /**
     * Records how a release resolved. Upserts rather than inserts so a re-run
     * after a partial failure is safe, and so a release the archive later
     * acquires art for can be promoted from `missing` to `stored`.
     */
    public function record(string $mbid, string $status): void
    {
        $now = Db::prepareDateForDb(new DateTimeImmutable());

        Craft::$app->getDb()->createCommand()->upsert(self::TABLE, [
            'releaseMbId' => $mbid,
            'status' => $status,
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => StringHelper::UUID(),
        ], [
            'status' => $status,
            'dateUpdated' => $now,
        ])->execute();
    }

    /**
     * How a single release resolved, or null if the sync hasn't reached it.
     */
    public function status(string $mbid): ?string
    {
        return (new Query())
            ->select(['status'])
            ->from(self::TABLE)
            ->where(['releaseMbId' => $mbid])
            ->scalar() ?: null;
    }

    /**
     * The subset of $mbids we actually hold art for, as a lookup set - for
     * callers deciding whether to render an image at all.
     *
     * @param string[] $mbids
     * @return array<string, int>
     */
    public function storedMbIds(array $mbids): array
    {
        if (!$mbids) {
            return [];
        }

        return array_flip((new Query())
            ->select(['releaseMbId'])
            ->from(self::TABLE)
            ->where(['releaseMbId' => $mbids, 'status' => self::STATUS_STORED])
            ->column());
    }
}
