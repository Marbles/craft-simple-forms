<?php
namespace rias\simpleforms\elements\db;

use Craft;
use craft\base\FieldInterface;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use ns\prefix\elements\Product;
use rias\simpleforms\elements\Form;
use rias\simpleforms\SimpleForms;

class SubmissionsQuery extends ElementQuery
{
    public $orderBy;
    public $title;
    public $authorId;
    public $formId;
    public $formHandle;
    public $submittedFrom;

    public function orderBy($value)
    {
        $this->orderBy = $value;

        return $this;
    }

    public function title($value)
    {
        $this->title = $value;

        return $this;
    }

    public function authorId($value)
    {
        $this->authorId = $value;

        return $this;
    }

    public function formId($value)
    {
        $this->formId = $value;

        return $this;
    }

    public function formHandle($value)
    {
        $this->formHandle = $value;

        return $this;
    }

    public function submittedFrom($value)
    {
        $this->submittedFrom = $value;

        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->contentTable = '{{%simple-forms_content}}';

        $this->joinElementTable('simple-forms_submissions');
        if (!in_array(['INNER JOIN', '{{%simple-forms_forms}}', '{{%simple-forms_forms}}.id = `simple-forms_submissions`.formId'], $this->join ?? [])) {
            $this->innerJoin('{{%simple-forms_forms}}', '{{%simple-forms_forms}}.id = `simple-forms_submissions`.formId');
        }

        $this->addSelect('simple-forms_submissions.id,
                           simple-forms_submissions.authorId,
                           simple-forms_submissions.ipAddress,
                           simple-forms_submissions.userAgent,
                           simple-forms_submissions.submittedFrom,
                           simple-forms_submissions.dateCreated,
                           simple-forms_submissions.dateUpdated,
                           simple-forms_submissions.uid,
                           {{%simple-forms_forms}}.id as formId,
                           {{%simple-forms_forms}}.name as formName');

        if ($this->id) {
            $this->andWhere(Db::parseParam('simple-forms_submissions.id', $this->id));
        }
        if ($this->authorId) {
            $this->andWhere(Db::parseParam('simple-forms_submissions.authorId', $this->authorId));
        }
        if ($this->formId) {
            $this->andWhere(Db::parseParam('simple-forms_submissions.formId', $this->formId));
        }
        if ($this->formHandle) {
            $this->andWhere(Db::parseParam('{{%simple-forms_forms}}.handle', $this->formHandle));
        }
        if ($this->submittedFrom) {
            $this->andWhere(Db::parseParam('{{%simple-forms_forms}}.submittedFrom', $this->submittedFrom));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function customFields(): array
    {
        return Craft::$app->getFields()->getAllFields('simple-forms');
    }
}
