<?php
namespace rias\simpleforms\models;

use craft\base\Model;

class SimpleFormsModel extends Model
{
    // Element types
    const ElementTypeForm = 'SimpleFormsForm';
    const ElementTypeSubmission = 'Submission';

    // Field context
    const FieldContext = 'simple-forms';

    //Field content
    const FieldContent = 'simple-forms_content';

    // Setting types
    const SettingGeneral = 'general';
    const SettingSubmissions = 'submissions';
    const SettingExport = 'export';
    const SettingAntispam = 'antispam';
    const SettingRecaptcha = 'recaptcha';
    const SettingsTemplatePaths = 'templates';
}
