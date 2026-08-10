<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * m260717_075055_create_tealfm_listens_table migration.
 */
class m260717_075055_create_tealfm_listens_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable('{{%tealfm_listens}}', [
            'id' => $this->primaryKey(),
            'uri' => $this->string()->notNull(),
            'trackName' => $this->string()->notNull(),
            'artistNames' => $this->json()->notNull(),
            'releaseName' => $this->string()->null(),
            'releaseMbId' => $this->string()->null(),
            'playedTime' => $this->dateTime()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%tealfm_listens}}', 'uri', true);
        $this->createIndex(null, '{{%tealfm_listens}}', 'playedTime', false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%tealfm_listens}}');

        return true;
    }
}
