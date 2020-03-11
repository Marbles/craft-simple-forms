<?php
namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\StringHelper;
use craft\records\Field;
use rias\simpleforms\SimpleForms;
use yii\db\Schema;

/**
 * simple-forms - Install service
 */
class InstallService extends Component
{
    /**
     * Install essential information.
     *
     * @param array $settings
     * @throws \Throwable
     */
    public function install(array $settings)
    {
        // Get fields to install
        $fields = $settings['fields'];
        // Validate fields
        if (is_array($fields) && count($fields)) {
            // Set field context and content
            Craft::$app->getContent()->fieldContext = 'simple-forms';
            Craft::$app->getContent()->contentTable = '{{%simple-forms_content}}';

            // Create fields
            foreach ($fields as $field) {
                $fieldConfig = [
                    'name' => Craft::t('simple-forms', $field['name']),
                    'handle' => isset($field['handle']) ? $field['handle'] : StringHelper::camelCase(Craft::t('simple-forms', $field['name'])),
                    'translationMethod' => isset($field['translationMethod']) ? $field['translationMethod'] : 'site',
                    'translationKeyFormat' => isset($field['translationKeyFormat']) ? $field['translationKeyFormat'] : null,
                    'type' => $field['type'],
                ];

                if (isset($field['instructions'])) {
                    $fieldConfig['instructions'] = $field['instructions'];
                }
                if (isset($field['settings'])) {
                    $fieldConfig['settings'] = $field['settings'];
                }
                $field = Craft::$app->getFields()->createField($fieldConfig);
                Craft::$app->getFields()->saveField($field);
            }
        }
    }
}
