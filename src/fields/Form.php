<?php

namespace rias\simpleforms\fields;

use Craft;
use craft\fields\BaseRelationField;
use rias\simpleforms\elements\Form as FormElement;

/**
 * Form fieldtype.
 */
class Form extends BaseRelationField
{
    public $allowMultipleSources = false;

    /**
     * {@inheritdoc}
     */
    public static function displayName(): string
    {
        return Craft::t('simple-forms', 'Forms (Simple Forms)');
    }

    protected static function elementType(): string
    {
        return FormElement::class;
    }

    /**
     * Returns the default [[selectionLabel]] value.
     *
     * @return string The default selection label
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('simple-forms', 'Choose a form');
    }
}
