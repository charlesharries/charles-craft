<?php

namespace helpers\tealfm;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateTimeImmutable;
use DateTimeInterface;

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

                $count++;
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $count;
    }

    public function latestPlayedTime(): ?DateTimeImmutable
    {
        $max = (new Query())
            ->from(self::TABLE)
            ->max('[[playedTime]]');

        return $max ? new DateTimeImmutable($max) : null;
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
            'playedTime' => new DateTimeImmutable($row['playedTime']),
        ], $rows);
    }
}
