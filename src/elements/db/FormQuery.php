<?php

namespace rias\simpleforms\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class FormQuery extends ElementQuery
{
    public $name;
    public $handle;
    public $groupId;

    /**
     * {@inheritdoc}
     */
    public function __construct($elementType, array $config = [])
    {
        // Default orderBy
        if (!isset($config['orderBy'])) {
            $config['orderBy'] = 'simple-forms_forms.name';
        }
        parent::__construct($elementType, $config);
    }

    public function name($value)
    {
        $this->name = $value;

        return $this;
    }

    public function handle($value)
    {
        $this->handle = $value;

        return $this;
    }

    public function groupId($value)
    {
        $this->groupId = $value;

        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('simple-forms_forms');

        $this->addSelect('simple-forms_forms.id,
                               elements.fieldLayoutId,
                               simple-forms_forms.redirectEntryId,
                               simple-forms_forms.name,
                               simple-forms_forms.handle,
                               simple-forms_forms.groupId,
                               simple-forms_forms.titleFormat,
                               simple-forms_forms.submitAction,
                               simple-forms_forms.submitButton,
                               simple-forms_forms.afterSubmit,
                               simple-forms_forms.afterSubmitText,
                               simple-forms_forms.submissionEnabled,
                               simple-forms_forms.displayTabTitles,
                               simple-forms_forms.redirectUrl,
                               simple-forms_forms.sendCopy,
                               simple-forms_forms.sendCopyTo,
                               simple-forms_forms.notificationEnabled,
                               simple-forms_forms.notificationFilesEnabled,
                               simple-forms_forms.notificationRecipients,
                               simple-forms_forms.notificationSubject,
                               simple-forms_forms.confirmationSubject,
                               simple-forms_forms.notificationSenderName,
                               simple-forms_forms.confirmationSenderName,
                               simple-forms_forms.notificationSenderEmail,
                               simple-forms_forms.confirmationSenderEmail,
                               simple-forms_forms.notificationReplyToEmail,
                               simple-forms_forms.formTemplate,
                               simple-forms_forms.tabTemplate,
                               simple-forms_forms.fieldTemplate,
                               simple-forms_forms.notificationTemplate,
                               simple-forms_forms.confirmationTemplate');

        if ($this->handle) {
            $this->subQuery->andWhere(Db::parseParam('simple-forms_forms.handle', $this->handle));
        }
        if ($this->name) {
            $this->subQuery->andWhere(Db::parseParam('simple-forms_forms.name', $this->name));
        }
        if ($this->groupId) {
            $this->subQuery->andWhere(Db::parseParam('simple-forms_forms.groupId', $this->groupId));
        }

        return parent::beforePrepare();
    }
}
