<?php

namespace helpers\tealfm;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateTimeImmutable;
use yii\db\Expression;

/**
 * Projects the store of album art metadata in the database.
 */
class TealFmAlbumArtRepository
{
    const TABLE = '{{%tealfm_album_art}}';

    const STATUS_STORED = 'stored';
    const STATUS_MISSING = 'missing';

    /**
     * Every release we hold a listen for but haven't resolved art for yet.
     *
     * @return string[]
     */
    public function unresolvedMbIds(): array
    {
        $played = (new Query())
            ->select(['releaseMbId'])
            ->from(TealFmListenRepository::TABLE)
            ->where(['not', ['releaseMbId' => null]])
            ->groupBy('releaseMbId')
            ->orderBy(new Expression('MAX([[playedTime]]) DESC'))
            ->column();

        // Listens might be uppercase UUIDs, but art is always lowercase: normalise!
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
     * Records how a release resolved.
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
