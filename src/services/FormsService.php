<?php
namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use craft\base\Field;
use craft\helpers\StringHelper;
use Exception;
use rias\simpleforms\elements\db\FormQuery;
use rias\simpleforms\elements\Form;
use rias\simpleforms\records\FormRecord;
use rias\simpleforms\SimpleForms;

/**
 * simple-forms - Forms service
 */
class FormsService extends Component
{
    private $_fields = array();
    private $_namespaces = array();

    /**
     * Returns a criteria model for AmForms_Form elements.
     *
     * @param array $attributes
     *
     * @throws Exception
     * @return FormQuery
     */
    public function getQuery(array $attributes = [])
    {
        return new FormQuery(Form::class, $attributes);
    }

    /**
     * Get all forms.
     *
     * @param string $indexBy
     * @return array|\craft\base\ElementInterface[]|null
     * @throws Exception
     */
    public function getAllForms($indexBy = 'id')
    {
        return $this->getQuery(['orderBy' => 'name', 'indexBy' => $indexBy, 'limit' => null])->all();
    }

    /**
     * Get a form by its ID.
     *
     * @param int $id
     *
     * @return array|\craft\base\ElementInterface|null
     * @throws Exception
     */
    public function getFormById($id)
    {
        return $this->getQuery()->id($id)->one();
    }

    /**
     * Get a form by its handle.
     *
     * @param string $handle
     *
     * @return array|\craft\base\ElementInterface|null
     * @throws Exception
     */
    public function getFormByHandle($handle)
    {
        return $this->getQuery()->handle($handle)->one();
    }

    /**
     * Save a form.
     *
     * @param Form $form
     *
     * @throws Exception
     * @return bool
     * @throws \Throwable
     */
    public function saveForm(Form $form)
    {
        // Is submissions or notifications enabled?
        if (! $form->submissionEnabled && ! $form->notificationEnabled) {
            $form->addError('submissionEnabled', Craft::t('simple-forms', 'Submissions or notifications must be enabled, otherwise you will lose the submission.'));
            $form->addError('notificationEnabled', Craft::t('simple-forms', 'Notifications or submissions must be enabled, otherwise you will lose the submission.'));
        }

        if (! $form->hasErrors()) {
            // Save the element!
            if (Craft::$app->getElements()->saveElement($form)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a form.
     *
     * @param AmForms_FormModel $form
     *
     * @throws Exception
     * @return bool
     */
    public function deleteForm(AmForms_FormModel $form)
    {
        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

        try {
            // Delete export files
            craft()->amForms_exports->deleteExportFilesForForm($form);

            // Delete the field layout
            craft()->fields->deleteLayoutById($form->fieldLayoutId);

            // Delete submission elements
            $submissionIds = craft()->db->createCommand()
                ->select('id')
                ->from('amforms_submissions')
                ->where(array('formId' => $form->id))
                ->queryColumn();
            craft()->elements->deleteElementById($submissionIds);

            // Delete the element and form
            craft()->elements->deleteElementById($form->id);

            if ($transaction !== null) {
                $transaction->commit();
            }

            return true;
        } catch (\Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }

        return false;
    }

    /**
     * Get unique name and handle for a form.
     *
     * @param AmForms_FormModel $form
     */
    public function getUniqueNameAndHandle(AmForms_FormModel $form)
    {
        $slugWordSeparator = craft()->config->get('slugWordSeparator');
        $maxSlugIncrement = craft()->config->get('maxSlugIncrement');

        for ($i = 0; $i < $maxSlugIncrement; $i++) {
            $testName = $form->name;

            if ($i > 0) {
                $testName .= $slugWordSeparator.$i;
            }

            $originalName = $form->name;
            $originalHandle = $form->handle;
            $form->name = $testName;
            $form->handle = StringHelper::toCamelCase($form->name);

            $totalForms = craft()->db->createCommand()
                ->select('count(id)')
                ->from('amforms_forms')
                ->where('name=:name AND handle=:handle', array(
                    ':name' => $form->name,
                    ':handle' => $form->handle,
                ))
                ->queryScalar();

            if ($totalForms ==  0) {
                return;
            }
            else {
                $form->name = $originalName;
                $form->handle = $originalHandle;
            }
        }

        throw new Exception(Craft::t('Could not find a unique name and handle for this form.'));
    }

    /**
     * Get a namespace for a form.
     *
     * @param Form $form
     * @param bool $createNewOnEmpty
     *
     * @return false|string
     */
    public function getNamespaceForForm(Form $form, $createNewOnEmpty = true)
    {
        if (! isset($this->_namespaces[ $form->id ]) && $createNewOnEmpty) {
            $this->_namespaces[ $form->id ] = 'form_'.StringHelper::randomString(10);
        }

        return isset($this->_namespaces[ $form->id ]) ? $this->_namespaces[ $form->id ] : false;
    }

    /**
     * Display a field.
     *
     * @param Form $form
     * @param string $handle
     *
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function displayField(Form $form, $handle)
    {
        // Get submission model
        $submission = SimpleForms::$plugin->submissionsService->getActiveSubmission($form);

        // Set namespace, if one was set
        $namespace = $this->getNamespaceForForm($form, false);
        if ($namespace) {
            Craft::$app->getView()->setNamespace($namespace);
        }

        // Get template path
        $fieldTemplateInfo = SimpleForms::$plugin->simpleFormsService->getDisplayTemplateInfo('field', $form->fieldTemplate);

        // Get the current templates path so we can restore it at the end of this function
        $siteTemplatesPath = Craft::$app->getView()->getTemplatesPath();
        $pluginTemplatePath = SimpleForms::$plugin->getBasePath() . '/templates/_display/templates/';

        // Do we have the current form fields?
        if (! isset($this->_fields[$form->id])) {
            $this->_fields[$form->id] = array();
            $supportedFields = SimpleForms::$plugin->fieldsService->getSupportedFieldTypes();

            // Get tabs
            foreach ($form->getFieldLayout()->getTabs() as $tab) {
                // Get tab's fields
                /** @var Field $field */
                foreach ($tab->getFields() as $field) {
                    // Get actual field
                    if (! in_array(get_class($field), $supportedFields)) {
                        // We don't display unsupported fields
                        continue;
                    }

                    // Reset templates path for input and get field input
                    Craft::$app->getView()->setTemplatesPath($pluginTemplatePath);
                    $input = $field->getInputHtml($submission->getFieldValue($field->handle));

                    // Get field HTML
                    Craft::$app->getView()->setTemplatesPath($fieldTemplateInfo['path']);
                    $fieldHtml = Craft::$app->getView()->renderTemplate($fieldTemplateInfo['template'], [
                        'form'      => $form,
                        'field'     => $field,
                        'fieldType' => get_class($field),
                        'input'     => $input,
                        'required'  => $field->required,
                        'element'   => $submission,
                        'namespace' => $namespace
                    ]);

                    // Add to fields
                    $this->_fields[$form->id][$field->handle] = $fieldHtml;
                }
            }
        }

        // Restore the templates path variable to it's original value
        Craft::$app->getView()->setTemplatesPath($siteTemplatesPath);

        // Reset namespace
        if ($namespace) {
            Craft::$app->getView()->setNamespace(null);
        }

        // Return field!
        if (isset($this->_fields[$form->id][$handle])) {
            return new \Twig_Markup($this->_fields[$form->id][$handle], Craft::$app->getView()->getTwig()->getCharset());
        } else {
            return null;
        }
    }

    /**
     * Display a form.
     *
     * @param Form $form
     *
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function displayForm(Form $form)
    {
        // Get submission model
        $submission = SimpleForms::$plugin->submissionsService->getActiveSubmission($form);

        // Set namespace
        $namespace = 'form_'.StringHelper::randomString(10);
        Craft::$app->getView()->setNamespace($namespace);

        // Build field HTML
        $tabs = [];
        $supportedFields = SimpleForms::$plugin->fieldsService->getSupportedFieldTypes();
        $fieldTemplateInfo = SimpleForms::$plugin->simpleFormsService->getDisplayTemplateInfo('field', $form->fieldTemplate);

        // Get the current templates path so we can restore it at the end of this function
        $siteTemplatesPath = Craft::$app->getView()->getTemplatesPath();
        $pluginTemplatePath = SimpleForms::$plugin->getBasePath() . '/templates/_display/templates/';

        foreach ($form->getFieldLayout()->getTabs() as $tab) {
            // Tab information
            $tabs[$tab->id] = [
                'info'   => $tab,
                'fields' => []
            ];

            // Tab fields
            $fields = $tab->getFields();
            /** @var Field $field */
            foreach ($fields as $field) {
                // Get actual field
                if (! in_array(get_class($field), $supportedFields)) {
                    // We don't display unsupported fields
                    continue;
                }

                // Reset templates path for input and get field input
                Craft::$app->getView()->setTemplatesPath($pluginTemplatePath);
                $input = $field->getInputHtml($submission->getFieldValue($field->handle));

                // Get field HTML
                Craft::$app->getView()->setTemplatesPath($fieldTemplateInfo['path']);
                $fieldHtml = Craft::$app->getView()->renderTemplate($fieldTemplateInfo['template'], [
                    'form'      => $form,
                    'field'     => $field,
                    'fieldType' => get_class($field),
                    'input'     => $input,
                    'required'  => $field->required,
                    'element'   => $submission,
                    'namespace' => $namespace
                ]);

                // Add to tabs
                $tabs[$tab->id]['fields'][] = $fieldHtml;
            }
        }

        // Restore the templates path variable to it's original value
        Craft::$app->getView()->setTemplatesPath($siteTemplatesPath);

        // Build tab HTML
        $variables = [
            'form'    => $form,
            'tabs'    => $tabs,
            'element' => $submission
        ];
        $bodyHtml = SimpleForms::$plugin->simpleFormsService->renderDisplayTemplate('tab', $form->tabTemplate, $variables);

        // Use AntiSpam?
        $antispamHtml = SimpleForms::$plugin->antiSpamService->render();

        // Use reCAPTCHA?
        $recaptchaHtml = SimpleForms::$plugin->recaptchaService->render();

        // Build our complete form
        $variables = [
            'form'      => $form,
            'body'      => $bodyHtml,
            'antispam'  => $antispamHtml,
            'recaptcha' => $recaptchaHtml,
            'element'   => $submission,
            'namespace' => $namespace
        ];
        $formHtml = SimpleForms::$plugin->simpleFormsService->renderDisplayTemplate('form', $form->formTemplate, $variables);

        // Reset namespace
        Craft::$app->getView()->setNamespace(null);

        // Parse form
        return new \Twig_Markup($formHtml, Craft::$app->getView()->getTwig()->getCharset());
    }
}
