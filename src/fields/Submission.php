<?php
namespace rias\simpleforms\fields;

use Craft;
use craft\base\Field;
use craft\fields\BaseRelationField;

/**
 * Form fieldtype
 */
class Submission extends BaseRelationField
{
    public $allowMultipleSources = true;

    protected static function elementType(): string
    {
        return \rias\simpleforms\elements\Submission::class;
    }

    /**
     * Returns the default [[selectionLabel]] value.
     *
     * @return string The default selection label
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('simple-forms', 'Choose a submission');
    }
}
