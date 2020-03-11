<?php
namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use craft\fields\Assets;
use craft\fields\Checkboxes;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Email;
use craft\fields\MissingField;
use craft\fields\MultiSelect;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\records\Field;

/**
 * simple-forms - Fields service
 */
class FieldsService extends Component
{
    /**
     * Get support fields.
     *
     * @param array $fieldTypes
     *
     * @return array
     */
    public function getProperFieldTypes($fieldTypes)
    {
        $basicFields = [];
        $advancedFields = [];
        $fieldTypeGroups = [];

        // Supported fields for displayForm functionality
        $supported = $this->getSupportedFieldTypes();

        // Set allowed fields
        foreach ($fieldTypes as $key => $fieldType) {
            if (in_array($fieldType, $supported)) {
                $basicFields[$key] = $fieldType;
            } else {
                $advancedFields[$key] = $fieldType;
            }
        }

        $fieldTypeGroups['basic'] = ['optgroup' => Craft::t('simple-forms', 'Basic fields')];
        foreach ($basicFields as $key => $fieldType) {
            $fieldTypeGroups[$fieldType] = $fieldType;
        }

        if(Craft::$app->getUser()->getIdentity()->admin) {
            $fieldTypeGroups['advanced'] = ['optgroup' => Craft::t('simple-forms', 'Advanced fields')];
            foreach ($advancedFields as $key => $fieldType) {
                $fieldTypeGroups[$fieldType] = $fieldType;
            }
        }

        return $fieldTypeGroups;
    }

    /**
     * Get supported field types.
     *
     * @return array
     */
    public function getSupportedFieldTypes()
    {
        return [
            Assets::class,
            Checkboxes::class,
            Date::class,
            Dropdown::class,
            MultiSelect::class,
            Number::class,
            PlainText::class,
            Email::class,
            RadioButtons::class,
        ];
    }
}
