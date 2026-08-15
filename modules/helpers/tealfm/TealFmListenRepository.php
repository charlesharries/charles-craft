<?php

namespace helpers\tealfm;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class TealFmListenRepository
{
    const TABLE = '{{%tealfm_listens}}';

    /**
     * Upserts a batch of normalized plays (as produced by TealFmClient),
     * keyed on `uri` so re-running a sync over overlapping plays is safe.
     */
    public function upsertMany(array $plays): int
    {
        $db = Craft::$app->getDb();
        $now = Db::prepareDateForDb(new DateTimeImmutable());
        $known = $this->knownUris(array_filter(array_column($plays, 'uri')));
        $count = 0;

        $transaction = $db->beginTransaction();

        try {
            foreach ($plays as $play) {
                if (!$play['uri'] || !$play['playedTime']) {
                    continue;
                }

                $values = [
                    'trackName' => $play['trackName'],
                    'artistNames' => $play['artistNames'],
                    'releaseName' => $play['releaseName'],
                    'releaseMbId' => $play['releaseMbId'],
                    'playedTime' => Db::prepareDateForDb($play['playedTime']),
                    'dateUpdated' => $now,
                ];

                $db->createCommand()->upsert(self::TABLE, [
                    'uri' => $play['uri'],
                    'dateCreated' => $now,
                    'uid' => StringHelper::UUID(),
                    ...$values,
                ], $values)->execute();

                // Marking it known as we go also keeps a URI that shows up
                // twice in one batch from counting twice.
                if (!isset($known[$play['uri']])) {
                    $known[$play['uri']] = true;
                    $count++;
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $count;
    }

    /**
     * What's the most recent thing we fetched from $collection?
     *
     * Use the record's TID rather than the playedTime in case we're syncing
     * something that was played a little while ago. Per collection because a
     * URI carries its collection in the middle, so a max across both is only
     * ever the NSID that sorts last.
     */
    public function latestUri(string $collection): ?string
    {
        return (new Query())
            ->from(self::TABLE)
            ->where(['like', 'uri', "/$collection/"])
            ->max('[[uri]]');
    }

    /**
     * Returns the subset of $uris that's already stored, as a lookup set.
     */
    protected function knownUris(array $uris): array
    {
        if (!$uris) {
            return [];
        }

        $found = (new Query())
            ->select(['uri'])
            ->from(self::TABLE)
            ->where(['uri' => $uris])
            ->column();

        return array_flip($found);
    }

    /**
     * Db::prepareDateForDb() writes UTC into a column that carries no zone of
     * its own, so reads have to say so - otherwise PHP reads them back in the
     * system time zone and every value silently shifts by the UTC offset.
     */
    protected static function toDateTime(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    /**
     * Returns listens played in ($start, $end), newest first, not inclusive
     * of $end.
     */
    public function between(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $rows = (new Query())
            ->select(['uri', 'trackName', 'artistNames', 'releaseName', 'releaseMbId', 'playedTime'])
            ->from(self::TABLE)
            ->where(['>=', 'playedTime', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'playedTime', Db::prepareDateForDb($end)])
            ->orderBy(['playedTime' => SORT_DESC])
            ->all();

        return array_map(self::hydrate(...), $rows);
    }

    /**
     * When each play in [$start, $end) was played, and nothing else - enough
     * to count days by, without hydrating a month of rows to throw all but
     * one column of them away.
     *
     * @return DateTimeImmutable[]
     */
    public function playedTimesBetween(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $times = (new Query())
            ->select(['playedTime'])
            ->from(self::TABLE)
            ->where(['>=', 'playedTime', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'playedTime', Db::prepareDateForDb($end)])
            ->column();

        return array_map(self::toDateTime(...), $times);
    }

    /**
     * Reshapes a stored row into the array format TealFmClient::normalize()
     * produces, plus the joined `artist` string every caller ends up needing.
     */
    public static function hydrate(array $row): array
    {
        $artistNames = json_decode($row['artistNames'], true) ?? [];

        return [
            'uri' => $row['uri'],
            'trackName' => $row['trackName'],
            'artistNames' => $artistNames,
            'artist' => implode(', ', $artistNames),
            'releaseName' => $row['releaseName'],
            'releaseMbId' => $row['releaseMbId'],
            'playedTime' => self::toDateTime($row['playedTime']),
        ];
    }
}
