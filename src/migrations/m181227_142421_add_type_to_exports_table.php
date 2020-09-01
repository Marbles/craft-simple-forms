<?php

namespace rias\simpleforms\migrations;

use craft\db\Migration;

/**
 * m181227_142421_add_type_to_exports_table migration.
 */
class m181227_142421_add_type_to_exports_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%simple-forms_exports}}', 'type', $this->string());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%simple-forms_exports}}', 'type');
    }
}
