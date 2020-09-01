<?php

namespace rias\simpleforms\elements;

use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\db\Query;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use rias\simpleforms\elements\actions\ExportElementAction;
use rias\simpleforms\elements\db\SubmissionsQuery;
use rias\simpleforms\models\export\ExportValueInterface;
use rias\simpleforms\SimpleForms;

/**
 *  Element.
 *
 * Element is the base class for classes representing elements in terms of objects.
 *
 * @property FieldLayout|null $fieldLayout           The field layout used by this element
 * @property array $htmlAttributes        Any attributes that should be included in the element’s DOM representation in the Control Panel
 * @property int[] $supportedSiteIds      The site IDs this element is available in
 * @property string|null $uriFormat             The URI format used to generate this element’s URL
 * @property string|null $url                   The element’s full URL
 * @property \Twig_Markup|null $link                  An anchor pre-filled with this element’s URL and title
 * @property string|null $ref                   The reference string to this element
 * @property string $indexHtml             The element index HTML
 * @property bool $isEditable            Whether the current user can edit the element
 * @property string|null $cpEditUrl             The element’s CP edit URL
 * @property string|null $thumbUrl              The URL to the element’s thumbnail, if there is one
 * @property string|null $iconUrl               The URL to the element’s icon image, if there is one
 * @property string|null $status                The element’s status
 * @property Element $next                  The next element relative to this one, from a given set of criteria
 * @property Element $prev                  The previous element relative to this one, from a given set of criteria
 * @property Element $parent                The element’s parent
 * @property mixed $route                 The route that should be used when the element’s URI is requested
 * @property int|null $structureId           The ID of the structure that the element is associated with, if any
 * @property ElementQueryInterface $ancestors             The element’s ancestors
 * @property ElementQueryInterface $descendants           The element’s descendants
 * @property ElementQueryInterface $children              The element’s children
 * @property ElementQueryInterface $siblings              All of the element’s siblings
 * @property Element $prevSibling           The element’s previous sibling
 * @property Element $nextSibling           The element’s next sibling
 * @property bool $hasDescendants        Whether the element has descendants
 * @property int $totalDescendants      The total number of descendants that the element has
 * @property string $title                 The element’s title
 * @property string|null $serializedFieldValues Array of the element’s serialized custom field values, indexed by their handles
 * @property array $fieldParamNamespace   The namespace used by custom field params on the request
 * @property string $contentTable          The name of the table this element’s content is stored in
 * @property string $fieldColumnPrefix     The field column prefix this element’s content uses
 * @property string $fieldContext          The field context this element’s content uses
 * @property \craft\base\ElementInterface[]|mixed|null|string order
 * @property \craft\base\ElementInterface[]|mixed|null|string authorId
 * @property \craft\base\ElementInterface[]|mixed|null|string formId
 * @property array $fields
 * @property \craft\base\ElementInterface[]|mixed|null|string formHandle
 *
 * http://pixelandtonic.com/blog/craft-element-types
 *
 * @author    Rias
 *
 * @since     1.0.0
 */
class Submission extends Element
{
    /** @var Form */
    public $form;

    public $formId;
    public $formName;
    public $formHandle;
    public $authorId;
    public $order;
    public $ipAddress;
    public $userAgent;
    public $submittedFrom;

    public $spamFree = true;

    /**
     * Returns whether this element type has content.
     *
     * @return bool
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * Returns whether this element type has titles.
     *
     * @return bool
     */
    public static function hasTitles(): bool
    {
        return true;
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

    /**
     * @return SubmissionsQuery
     */
    public static function find(): ElementQueryInterface
    {
        return new SubmissionsQuery(self::class);
    }

    /**
     * Returns the field layout used by this element.
     *
     * @throws \Exception
     */
    public function getFieldLayout()
    {
        return $this->getForm() ? $this->getForm()->getFieldLayout() : parent::getFieldLayout();
    }

    /**
     * Returns the fields associated with this form.
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getFields()
    {
        return $this->getForm()->getFields();
    }

    /**
     * Get the form model.
     *
     * @throws \Exception
     *
     * @return Form
     */
    public function getForm()
    {
        if (!isset($this->form)) {
            try {
                $this->form = SimpleForms::$plugin->forms->getFormById($this->formId);
            } catch (\Exception $e) {
                // Do nothing
            }
        }

        return $this->form;
    }

    public function export(array $fieldsToExport): array
    {
        $row = [];
        foreach ($fieldsToExport as $handle) {
            $value = $this->$handle;
            if (is_object($value)) {
                /** @var ExportValueInterface $class */
                $className = get_class($value);
                $classNameParts = explode('\\', $className);
                $classNameWithoutNamespace = array_pop($classNameParts);

                $class = 'rias\\simpleforms\\models\\export\\'.$classNameWithoutNamespace.'Export';
                $value = $class::toColumn($value);
            }
            $row[] = $value;
        }

        return $row;
    }

    /**
     * Returns the element's CP edit URL.
     *
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('simple-forms/submissions/edit/'.$this->id);
    }

    /**
     * Returns the name of the table this element's content is stored in.
     *
     * @return string
     */
    public function getContentTable(): string
    {
        return '{{%simple-forms_content}}';
    }

    /**
     * Returns the field context this element's content uses.
     *
     * @return string
     */
    public function getFieldContext(): string
    {
        return 'simple-forms';
    }

    /**
     * Returns this element type's sources.
     *
     * @param string|null $context
     *
     * @throws \Exception
     *
     * @return array|false
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key'         => '*',
                'label'       => Craft::t('simple-forms', 'All submissions'),
                'criteria'    => [],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
        ];

        $forms = SimpleForms::$plugin->forms->getAllForms();
        if (!empty($forms)) {
            /** @var Form $form */
            foreach ($forms as $form) {
                $sources[] = [
                    'key'         => 'formId:'.$form->id,
                    'label'       => $form->name,
                    'criteria'    => ['formId' => $form->id],
                    'defaultSort' => ['dateCreated', 'desc'],
                ];
            }
        }

        return $sources;
    }

    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        // Can the current user handle exports?
        /** @var User $user */
        $user = Craft::$app->getUser()->getIdentity();
        if ($user->can('accessSimpleFormsExports')) {
            // Get export action
            $actions[] = ExportElementAction::class;
        }

        // Get delete action
        $actions[] = new Delete([
            'confirmationMessage' => Craft::t('simple-forms', 'Are you sure you want to delete the selected submissions?'),
            'successMessage'      => Craft::t('simple-forms', 'Submissions deleted.'),
        ]);

        // Set actions
        return $actions;
    }

    protected static function defineTableAttributes(): array
    {
        $attributes = [
            'title'       => Craft::t('simple-forms', 'Title'),
            'formName'    => Craft::t('simple-forms', 'Form name'),
            'dateCreated' => Craft::t('simple-forms', 'Date created'),
            'dateUpdated' => Craft::t('simple-forms', 'Date updated'),
            'notes'       => Craft::t('simple-forms', 'Notes'),
        ];

        /** @var Field $field */
        foreach (Craft::$app->getFields()->getAllFields('simple-forms') as $field) {
            if (in_array(get_class($field), SimpleForms::$supportedFields)) {
                $attributes['field:'.$field->id] = Craft::t('site', $field->name);
            }
        }

        return $attributes;
    }

    /**
     * @param string $source
     *
     * @throws \Exception
     *
     * @return array
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [
            'title'       => Craft::t('simple-forms', 'Title'),
            'formName'    => Craft::t('simple-forms', 'Form name'),
            'dateCreated' => Craft::t('simple-forms', 'Date created'),
            'dateUpdated' => Craft::t('simple-forms', 'Date updated'),
            'notes'       => Craft::t('simple-forms', 'Notes'),
        ];

        if ($source === '*') {
            return $attributes;
        }

        $formId = explode(':', $source)[1];
        $form = SimpleForms::$plugin->forms->getFormById((int) $formId);
        $fields = $form->getFieldLayout()->getFields();

        /** @var Field $field */
        foreach ($fields as $field) {
            if (in_array(get_class($field), SimpleForms::$supportedFields)) {
                $attributes['field:'.$field->id] = Craft::t('site', $field->name);
            }
        }

        return $attributes;
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'notes':
                $notes = (string) (new Query())
                    ->select(['COUNT(*)'])
                    ->from('{{%simple-forms_notes}}')
                    ->where('submissionId=:submissionId', [':submissionId' => $this->id])
                    ->scalar();

                return sprintf('<a href="%s">%d</a>',
                    $this->getCpEditUrl().'/notes',
                    $notes
                );

            default:
                try {
                    return parent::tableAttributeHtml($attribute);
                } catch (\Exception $e) {
                    return '';
                }
        }
    }

    protected static function defineSortOptions(): array
    {
        $options = [
            //'formName'    => Craft::t('simple-forms', 'Form name'),
            'dateCreated' => Craft::t('simple-forms', 'Date created'),
            'dateUpdated' => Craft::t('simple-forms', 'Date updated'),
        ];

        /** @var Field $field */
        foreach (Craft::$app->getFields()->getAllFields('simple-forms') as $field) {
            $options[$field->handle] = $field->name;
        }

        return $options;
    }

    public static function searchableAttributes(): array
    {
        $attributes = ['id', 'title', 'formName'];

        /** @var Field $field */
        foreach (Craft::$app->getFields()->getAllFields('simple-forms') as $field) {
            $attributes[] = $field->handle;
        }

        return $attributes;
    }

    /**
     * @param bool $isNew
     *
     * @throws \yii\db\Exception
     */
    public function afterSave(bool $isNew)
    {
        $attributes = [
            'order'      => $this->order,
            'authorId'   => $this->authorId,
            'formId'     => $this->formId,
            'formHandle' => $this->formHandle,
            'userAgent'  => $this->userAgent,
        ];

        if ($isNew) {
            \Craft::$app->db->createCommand()
                ->insert('{{%simple-forms_submissions}}', array_merge(['id' => $this->id], $attributes))
                ->execute();
        } else {
            \Craft::$app->db->createCommand()
                ->update('{{%simple-forms_submissions}}', $attributes, ['id' => $this->id])
                ->execute();
        }

        parent::afterSave($isNew);
    }
}
