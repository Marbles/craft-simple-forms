<?php

namespace rias\simpleforms\records;

use Craft;
use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class FormGroupRecord extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%simple-forms_formgroups}}';
    }

    /**
     * Use the translated section name as the string representation.
     *
     * @return string
     */
    public function __toString()
    {
        $name = Craft::t('simple-forms', $this->name);

        return (string) $name;
    }
}
