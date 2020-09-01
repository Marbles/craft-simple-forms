<?php

namespace rias\simpleforms\migrations;

use craft\db\Migration;

/**
 * m190205_131237_add_group_id_to_forms_table migration.
 */
class m190205_131237_add_group_id_to_forms_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%simple-forms_forms}}', 'groupId', $this->integer()->null());
        $this->addForeignKey(null, '{{%simple-forms_forms}}', 'groupId', '{{%simple-forms_formgroups}}', 'id', 'SET NULL');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m190205_131237_add_group_id_to_forms_table cannot be reverted.\n";

        return false;
    }
}
