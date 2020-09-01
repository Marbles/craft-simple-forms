<?php

namespace rias\simpleforms\records;

use craft\db\ActiveRecord;
use craft\db\Query;
use craft\fields\Assets;
use craft\fields\Checkboxes;
use craft\fields\Dropdown;
use craft\fields\Entries;
use craft\fields\Lightswitch;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use rias\simpleforms\elements\Submission;
use rias\simpleforms\SimpleForms;

/**
 * @property int id
 * @property int formId
 * @property string name
 * @property string|null|object submissions
 * @property int total
 * @property string file
 * @property string type
 * @property bool finished
 * @property mixed map
 * @property mixed criteria
 */
class ExportRecord extends ActiveRecord
{
    public $startRightAway = false;

    /**
     * {@inheritdoc}
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
            [['name', 'file', 'type'], 'string'],
            [['map', 'criteria'], 'safe'],
            [['submissions'], 'each', 'rule' => ['integer']],
            [['startRightAway'], 'default', 'value' => false],
            [['finished'], 'default', 'value' => false],
        ];
    }

    /**
     * @return int
     */
    public function calculateTotal(): int
    {
        return (int) (new Query())
            ->select('COUNT(*)')
            ->from('{{%simple-forms_submissions}}')
            ->where('formId=:formId', [':formId' => $this->formId])
            ->scalar() ?? 0;
    }

    public function decodeAttributes()
    {
        $this->setAttribute('map', json_decode($this->map, true));
        $this->setAttribute('criteria', json_decode($this->criteria, true));

        return $this;
    }

    /**
     * @throws \Exception
     *
     * @return Submission[]
     */
    public function getSubmissions(): array
    {
        $params = [
            'limit'  => null,
            'formId' => $this->formId,
        ];

        if ($this->submissions) {
            $params['id'] = is_string($this->submissions) ? json_decode($this->submissions) : $this->submissions;
        }

        $criteria = SimpleForms::$plugin->submissions->getCriteria($params);

        // Do we even have criteria?
        if (!$this->criteria) {
            return $criteria->all();
        }

        $form = SimpleForms::$plugin->forms->getFormById($this->formId);

        // Gather related criteria
        $relatedTo = ['or'];

        // Get fields
        $fields = collect(SimpleForms::$plugin->exports->getExportFields($form))->filter(function ($field) {
            return isset($this->criteria[$field->id]);
        });

        foreach ($fields as $field) {
            // Add criteria based on field type
            switch (get_class($field)) {
                case Assets::class:
                case Entries::class:
                    foreach ($this->criteria[$field->id] as $criteriaValue) {
                        if (!empty($criteriaValue) && is_array($criteriaValue) && count($criteriaValue)) {
                            $relatedTo[] = $criteriaValue[0];
                        }
                    }
                    break;

                case Checkboxes::class:
                    $setCriteria = [];
                    foreach ($this->criteria[$field->id] as $criteriaValue) {
                        if (!empty($criteriaValue)) {
                            foreach ($criteriaValue as $subCriteriaValue) {
                                $setCriteria[] = '*"'.$subCriteriaValue.'"*';
                            }
                        }
                    }
                    $criteria->{$field->handle} = count($setCriteria) ? array_merge(['or'], $setCriteria) : '[]';
                    break;

                case Lightswitch::class:
                    $valueFound = false;
                    foreach ($this->criteria[$field->id] as $criteriaValue) {
                        if (!empty($criteriaValue)) {
                            $valueFound = true;
                            $criteria->{$field->handle} = $criteriaValue;
                        }
                    }
                    if (!$valueFound) {
                        $criteria->{$field->handle} = 'not 1';
                    }
                    break;

                case Dropdown::class:
                case PlainText::class:
                case RadioButtons::class:
                    $setCriteria = ['or'];
                    foreach ($this->criteria[$field->id] as $criteriaValue) {
                        if (!empty($criteriaValue)) {
                            $setCriteria[] = $criteriaValue;
                        }
                    }
                    $criteria->{$field->handle} = $setCriteria;
                    break;
            }
        }

        // Set relations criteria
        if (count($relatedTo) > 1) {
            $criteria->relatedTo = $relatedTo;
        }

        return $criteria->all();
    }
}
