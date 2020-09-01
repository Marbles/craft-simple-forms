<?php

namespace rias\simpleforms\migrations;

use craft\db\Migration;

/**
 * m190205_131053_create_formgroups_table migration.
 */
class m190205_131053_create_formgroups_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable(
            '{{%simple-forms_formgroups}}',
            [
                'id'            => $this->integer()->notNull(),
                'name'          => $this->string()->notNull(),
                'dateCreated'   => $this->dateTime()->notNull(),
                'dateUpdated'   => $this->dateTime()->notNull(),
                'uid'           => $this->uid(),
                'PRIMARY KEY(id)',
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m190205_131053_create_formgroups_table cannot be reverted.\n";

        return false;
    }
}
