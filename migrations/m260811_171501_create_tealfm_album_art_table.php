<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * m260811_171501_create_tealfm_album_art_table migration.
 */
class m260811_171501_create_tealfm_album_art_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // One row per release, not per listen - `tealfm_listens` is keyed on
        // plays, so the same album would carry this state a hundred times over.
        $this->createTable('{{%tealfm_album_art}}', [
            'id' => $this->primaryKey(),
            'releaseMbId' => $this->string(36)->notNull(),
            // 'stored' or 'missing'. A string rather than an enum so a third
            // status later is a code change instead of a migration.
            'status' => $this->string(16)->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%tealfm_album_art}}', 'releaseMbId', true);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%tealfm_album_art}}');

        return true;
    }
}
