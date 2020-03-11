<?php
namespace rias\simpleforms\elements;

use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\db\Query;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use rias\simpleforms\elements\db\FormQuery;
use rias\simpleforms\SimpleForms;

/**
 *
 * @property string $namespace
 * @property null|Entry $redirectEntry
 * @property array $fields
 */
class Form extends Element
{
    private $_fields;

    /** @var int */
    public $redirectEntryId;

    /** @var string */
    public $name;
    /** @var string */
    public $handle;
    /** @var string */
    public $titleFormat = "{dateCreated|date('D, d M Y H:i:s')}";
    /** @var string */
    public $submitAction;
    /** @var string */
    public $submitButton;
    /** @var string */
    public $afterSubmit;
    /** @var mixed */
    public $afterSubmitText;
    /** @var bool */
    public $submissionEnabled = true;
    /** @var bool */
    public $displayTabTitles = false;
    /** @var string */
    public $redirectUrl;
    /** @var bool */
    public $sendCopy = false;
    /** @var string */
    public $sendCopyTo;
    /** @var bool */
    public $notificationEnabled = true;
    /** @var bool */
    public $notificationFilesEnabled = false;
    /** @var string */
    public $notificationRecipients;
    /** @var string */
    public $notificationSubject;
    /** @var string */
    public $confirmationSubject;
    /** @var string */
    public $notificationSenderName;
    /** @var string */
    public $confirmationSenderName;
    /** @var string */
    public $notificationSenderEmail;
    /** @var string */
    public $confirmationSenderEmail;
    /** @var string */
    public $notificationReplyToEmail;
    /** @var string */
    public $formTemplate;
    /** @var string */
    public $tabTemplate;
    /** @var string */
    public $fieldTemplate;
    /** @var string */
    public $notificationTemplate;
    /** @var string */
    public $confirmationTemplate;

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        // Craft email settings
        $settings = Craft::$app->getSystemSettings()->getEmailSettings();
        $systemEmail = $settings->fromEmail;
        $systemName =  $settings->fromEmail;

        $this->notificationRecipients = $this->notificationRecipients ?: $systemEmail;
        $this->notificationSenderEmail = $this->notificationSenderEmail ?: $systemEmail;
        $this->confirmationSenderEmail = $this->confirmationSenderEmail ?: $systemEmail;
        $this->notificationReplyToEmail = $this->notificationReplyToEmail ?: $systemEmail;

        $this->notificationSenderName = $this->notificationSenderName ?: $systemName;
        $this->confirmationSenderName = $this->confirmationSenderName ?: $systemName;

        $this->notificationSubject = $this->notificationSubject ?: Craft::t('simple-forms', '{formName} form was submitted');
        $this->confirmationSubject = $this->confirmationSubject ?: Craft::t('simple-forms', 'Thanks for your submission.');
    }

    /**
     * Use the form handle as the string representation.
     *
     * @return string
     */
    function __toString()
    {
        return Craft::t('simple-forms', $this->name);
    }

    public static function find(): ElementQueryInterface
    {
        return new FormQuery(self::class);
    }

    /**
     * Return the element's fields.
     *
     * @return array
     */
    public function getFields()
    {
        if (! isset($this->_fields)) {
            $this->_fields = array();
            $layoutFields = $this->getFieldLayout()->getFields();
            /** @var Field $field */
            foreach ($layoutFields as $field) {
                $this->_fields[ $field->handle ] = $field;
            }
        }

        return $this->_fields;
    }

    /**
     * @inheritDoc BaseElementModel::isEditable()
     *
     * @return bool
     */
    public function getIsEditable(): bool
    {
        return Craft::$app->getUser()->checkPermission('accessAmFormsForms');
    }

    /**
     * Returns the element's CP edit URL.
     *
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('simple-forms/forms/edit/' . $this->id);
    }

    /**
     * Returns whether this element type has content.
     *
     * @return bool
     */
    public static function hasContent(): bool
    {
        return false;
    }

    /**
     * Returns whether this element type stores data on a per-locale basis.
     *
     * @return bool
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    protected static function defineSources(string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('simple-forms', 'All forms'),
                'criteria' => []
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'name' => Craft::t('simple-forms', 'Name'),
            'handle' => Craft::t('simple-forms', 'Handle'),
            'numberOfFields' => Craft::t('simple-forms', 'Number of fields'),
            'totalSubmissions' => Craft::t('simple-forms', 'Total submissions')
        ];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'name' => Craft::t('simple-forms', 'Name'),
            'handle' => Craft::t('simple-forms', 'Handle')
        ];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'handle':
                return '<code>' . $this->handle . '</code>';
                break;

            case 'numberOfFields':
                $totalFields = (new Query())
                    ->select('COUNT(*)')
                    ->from('{{%fieldlayoutfields}}')
                    ->where('layoutId=:layoutId', array(':layoutId' => $this->fieldLayoutId))
                    ->scalar();

                return '<a href="'.$this->getCpEditUrl().'#designer">'.$totalFields.'</a>';
                break;

            case 'totalSubmissions':
                $totalSubmissions = (new Query())
                    ->select('COUNT(*)')
                    ->from('{{%simple-forms_submissions}}')
                    ->where('formId=:formId', array(':formId' => $this->id))
                    ->scalar();

                return '<a href="'.UrlHelper::cpUrl('simple-forms/submissions').'">'.$totalSubmissions.'</a>';
                break;

            default:
                return parent::getTableAttributeHtml($attribute);
                break;
        }
    }

    /**
     * Defines which model attributes should be searchable.
     *
     * @return array
     */
    public static function defineSearchableAttributes(): array
    {
        return [
            'name',
            'handle'
        ];
    }

    public function getEditorHtml(): string
    {
        return sprintf('<div class="pane"><a class="btn submit" href="%s" target="_blank">%s</a></div>',
            $this->getCpEditUrl(),
            Craft::t('simple-forms', 'Edit form')
        );
    }

    /**
     * Return the form's redirect Entry.
     *
     * @return null|Entry
     */
    public function getRedirectEntry()
    {
        if ($this->redirectEntryId) {
            return Entry::find()->id($this->redirectEntryId)->one();
        }

        return null;
    }

    /**
     * Return the form's redirect URL.
     *
     * @return null|string
     */
    public function getRedirectUrl()
    {
        $entry = $this->getRedirectEntry();
        if ($entry) {
            return $entry->url;
        }
        return null;
    }

    /**
     * Get a namespace for this form.
     *
     * @return string
     */
    public function getNamespace()
    {
        return SimpleForms::$plugin->formsService->getNamespaceForForm($this);
    }

    /**
     * Display a field.
     *
     * @param string $handle
     *
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function displayField($handle)
    {
        return SimpleForms::$plugin->formsService->displayField($this, $handle);
    }

    /**
     * Display the form.
     *
     * With this we can display the Form FieldType on a front-end template.
     *
     * @example {{ entry.fieldHandle.first().displayForm() }}
     *
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function displayForm()
    {
        return SimpleForms::$plugin->formsService->displayForm($this);
    }

    /**
     * @param bool $isNew
     * @throws \yii\db\Exception
     */
    public function afterSave(bool $isNew)
    {
        $command = \Craft::$app->db->createCommand();

        $fields = [
            'redirectEntryId' => $this->redirectEntryId,
            'name' => $this->name,
            'handle' => $this->handle,
            'titleFormat' => $this->titleFormat ,
            'submitAction' => $this->submitAction,
            'submitButton' => $this->submitButton,
            'afterSubmit' => $this->afterSubmit,
            'afterSubmitText' => $this->afterSubmitText,
            'submissionEnabled' => $this->submissionEnabled ,
            'displayTabTitles' => $this->displayTabTitles ,
            'redirectUrl' => $this->redirectUrl,
            'sendCopy' => $this->sendCopy ,
            'sendCopyTo' => $this->sendCopyTo,
            'notificationEnabled' => $this->notificationEnabled ,
            'notificationFilesEnabled' => $this->notificationFilesEnabled ,
            'notificationRecipients' => $this->notificationRecipients,
            'notificationSubject' => $this->notificationSubject,
            'confirmationSubject' => $this->confirmationSubject,
            'notificationSenderName' => $this->notificationSenderName,
            'confirmationSenderName' => $this->confirmationSenderName,
            'notificationSenderEmail' => $this->notificationSenderEmail,
            'confirmationSenderEmail' => $this->confirmationSenderEmail,
            'notificationReplyToEmail' => $this->notificationReplyToEmail,
            'formTemplate' => $this->formTemplate,
            'tabTemplate' => $this->tabTemplate,
            'fieldTemplate' => $this->fieldTemplate,
            'notificationTemplate' => $this->notificationTemplate,
            'confirmationTemplate' => $this->confirmationTemplate,
        ];

        if ($isNew) {
                $command->insert('{{%simple-forms_forms}}', array_merge(['id' => $this->id], $fields))->execute();
        } else {
                $command->update('{{%simple-forms_forms}}', $fields, ['id' => $this->id])->execute();
        }

        parent::afterSave($isNew);
    }
}
