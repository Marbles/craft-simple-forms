<?php

namespace rias\simpleforms\records;

use craft\db\ActiveRecord;

class NoteRecord extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%simple-forms_notes}}';
    }

    public function rules()
    {
        return [
            [['name', 'text', 'submissionId'], 'required'],
        ];
    }
}
