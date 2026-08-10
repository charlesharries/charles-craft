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
     *
     * Returns the number of plays that weren't already stored - a sync that
     * only re-covers plays we've already seen reports 0, not the batch size.
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
     * The sync's high-water mark: the URI of the most recently *written*
     * record we hold. Every URI here shares one `at://<did>/<collection>/`
     * prefix and ends in a TID, so the highest URI is the newest record -
     * see TealFmClient::getPlaysAfter() for why write order, and not
     * `playedTime`, is what a sync has to page against.
     */
    public function latestUri(): ?string
    {
        return (new Query())
            ->from(self::TABLE)
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
     * Returns listens played since $since, newest first, reshaped to match
     * the array format TealFmClient::normalize() produces.
     */
    public function forPeriod(DateTimeInterface $since): array
    {
        $rows = (new Query())
            ->select(['uri', 'trackName', 'artistNames', 'releaseName', 'releaseMbId', 'playedTime'])
            ->from(self::TABLE)
            ->where(['>=', 'playedTime', Db::prepareDateForDb($since)])
            ->orderBy(['playedTime' => SORT_DESC])
            ->all();

        return array_map(fn ($row) => [
            'uri' => $row['uri'],
            'trackName' => $row['trackName'],
            'artistNames' => json_decode($row['artistNames'], true) ?? [],
            'releaseName' => $row['releaseName'],
            'releaseMbId' => $row['releaseMbId'],
            'playedTime' => self::toDateTime($row['playedTime']),
        ], $rows);
    }
}
