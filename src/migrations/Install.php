<?php

namespace rias\simpleforms\migrations;

use Craft;
use craft\db\Migration;

class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return bool return a false value to indicate the migration fails
     *              and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return bool return a false value to indicate the migration fails
     *              and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin.
     *
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        // simple-forms_submissions table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%simple-forms_submissions}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%simple-forms_submissions}}',
                [
                    'id'            => $this->integer()->notNull(),
                    'formId'        => $this->integer()->notNull(),
                    'formHandle'    => $this->string()->null(),
                    'order'         => $this->string()->null(),
                    'authorId'      => $this->integer()->null(),
                    'ipAddress'     => $this->string()->null(),
                    'userAgent'     => $this->text()->notNull(),
                    'submittedFrom' => $this->string()->null(),
                    'dateCreated'   => $this->dateTime()->notNull(),
                    'dateUpdated'   => $this->dateTime()->notNull(),
                    'uid'           => $this->uid(),
                    'PRIMARY KEY(id)',
                ]
            );
        }

        // simple-forms_forms table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%simple-forms_forms}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%simple-forms_forms}}',
                [
                    'id'                       => $this->integer()->notNull(),
                    'redirectEntryId'          => $this->integer()->null(),
                    'name'                     => $this->string()->notNull(),
                    'handle'                   => $this->string()->notNull(),
                    'titleFormat'              => $this->string()->notNull(),
                    'submitAction'             => $this->string()->null(),
                    'submitButton'             => $this->string()->null(),
                    'afterSubmit'              => $this->string()->null(),
                    'afterSubmitText'          => $this->text()->null(),
                    'submissionEnabled'        => $this->tinyInteger()->defaultValue(1),
                    'displayTabTitles'         => $this->tinyInteger()->defaultValue(0),
                    'redirectUrl'              => $this->string()->null(),
                    'sendCopy'                 => $this->tinyInteger()->defaultValue(0),
                    'sendCopyTo'               => $this->string()->null(),
                    'notificationEnabled'      => $this->tinyInteger()->defaultValue(1),
                    'notificationFilesEnabled' => $this->tinyInteger()->defaultValue(0),
                    'notificationRecipients'   => $this->string()->null(),
                    'notificationSubject'      => $this->string()->null(),
                    'confirmationSubject'      => $this->string()->null(),
                    'notificationSenderName'   => $this->string()->null(),
                    'confirmationSenderName'   => $this->string()->null(),
                    'notificationSenderEmail'  => $this->string()->null(),
                    'confirmationSenderEmail'  => $this->string()->null(),
                    'notificationReplyToEmail' => $this->string()->null(),
                    'formTemplate'             => $this->string()->null(),
                    'tabTemplate'              => $this->string()->null(),
                    'fieldTemplate'            => $this->string()->null(),
                    'notificationTemplate'     => $this->string()->null(),
                    'confirmationTemplate'     => $this->string()->null(),
                    'dateCreated'              => $this->datetime()->notNull(),
                    'dateUpdated'              => $this->datetime()->notNull(),
                    'uid'                      => $this->uid(),
                    'PRIMARY KEY(id)',
                ]
            );
        }

        // simple-forms_exports table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%simple-forms_exports}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%simple-forms_exports}}',
                [
                    'id'            => $this->primaryKey(),
                    'formId'        => $this->integer(11)->notNull(),
                    'name'          => $this->string(255)->null(),
                    'total'         => $this->integer()->defaultValue(0),
                    'totalCriteria' => $this->integer(10)->null(),
                    'finished'      => $this->tinyInteger(1)->unsigned()->notNull(),
                    'file'          => $this->string(255)->null(),
                    'map'           => $this->text(),
                    'criteria'      => $this->text(),
                    'submissions'   => $this->text(),
                    'dateCreated'   => $this->datetime()->notNull(),
                    'dateUpdated'   => $this->datetime()->notNull(),
                    'uid'           => $this->uid(),
                ]
            );
        }

        // simple-forms_content table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%simple-forms_content}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%simple-forms_content}}',
                [
                    'id'            => $this->primaryKey(),
                    'elementId'     => $this->integer()->notNull(),
                    'siteId'        => $this->integer()->notNull(),
                    'title'         => $this->string()->null(),
                    'uid'           => $this->uid(),
                    'dateCreated'   => $this->datetime()->notNull(),
                    'dateUpdated'   => $this->datetime()->notNull(),
                ]
            );
        }

        // simple-forms_notes table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%simple-forms_notes}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%simple-forms_notes}}',
                [
                    'id'           => $this->primaryKey(),
                    'submissionId' => $this->integer()->notNull(),
                    'name'         => $this->string()->null(),
                    'text'         => $this->text()->null(),
                    'dateCreated'  => $this->datetime()->notNull(),
                    'dateUpdated'  => $this->datetime()->notNull(),
                    'uid'          => $this->uid(),
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin.
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        // simple-forms_submissions table
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%simple-forms_submissions}}', 'id'),
            '{{%simple-forms_submissions}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );

        // simple-forms_forms table
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%simple-forms_forms}}', 'id'),
            '{{%simple-forms_forms}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );

        // simple-forms_content table
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%simple-forms_content}}', 'elementId'),
            '{{%simple-forms_content}}',
            'elementId',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%simple-forms_content}}', 'siteId'),
            '{{%simple-forms_content}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            null
        );

        // simple-forms_notes table
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%simple-forms_notes}}', 'id'),
            '{{%simple-forms_notes}}',
            'submissionId',
            '{{%simple-forms_submissions}}',
            'id',
            'CASCADE',
            null
        );
    }

    /**
     * Populates the DB with the default data.
     *
     * @return void
     */
    protected function insertDefaultData()
    {
    }

    /**
     * Removes the tables needed for the Records used by the plugin.
     *
     * @return void
     */
    protected function removeTables()
    {
        // contactform_submissions table
        $this->dropTableIfExists('{{%simple-forms_content}}');
        $this->dropTableIfExists('{{%simple-forms_forms}}');
        $this->dropTableIfExists('{{%simple-forms_notes}}');
        $this->dropTableIfExists('{{%simple-forms_exports}}');
        $this->dropTableIfExists('{{%simple-forms_submissions}}');
    }
}
