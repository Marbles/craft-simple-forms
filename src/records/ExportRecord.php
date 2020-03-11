<?php
namespace rias\simpleforms\records;

use craft\db\ActiveRecord;

/**
 * @property int id
 * @property int formId
 * @property string submissions
 * @property int total
 * @property string file
 * @property boolean finished
 * @property mixed map
 */
class ExportRecord extends ActiveRecord
{
    public $startRightAway = false;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%simple-forms_exports}}';
    }

    public function rules()
    {
        return [
            // the name, email, subject and body attributes are required
            [['id', 'formId', 'total', 'totalCriteria'], 'integer'],
            [['finished', 'startRightAway'], 'boolean'],
            [['name', 'file'], 'string'],
            [['map', 'criteria'], 'safe'],
            [['submissions'], 'each', 'rule' => ['integer']],
            [['startRightAway'], 'default', 'value' => false],
            [['finished'], 'default', 'value' => false],
        ];
    }
}
