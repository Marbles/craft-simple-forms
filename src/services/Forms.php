<?php

namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use craft\base\Field;
use craft\db\Query;
use craft\helpers\StringHelper;
use Exception;
use rias\simpleforms\elements\db\FormQuery;
use rias\simpleforms\elements\Form;
use rias\simpleforms\SimpleForms;
use Twig_Markup;

/**
 * simple-forms - Forms service.
 */
class Forms extends Component
{
    private $_fields = [];
    private $_namespaces = [];

    /**
     * Returns a criteria model for AmForms_Form elements.
     *
     * @param array $attributes
     *
     * @throws Exception
     *
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
     *
     * @throws Exception
     *
     * @return array|\craft\base\ElementInterface[]|null
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
     * @throws Exception
     *
     * @return Form
     */
    public function getFormById($id): Form
    {
        $form = $this->getQuery()->id($id)->one();

        if (!$form || !$form instanceof Form) {
            throw new Exception(Craft::t('simple-forms', 'No form exists with the ID “{id}”.', ['id' => $id]));
        }

        return $form;
    }

    /**
     * Get a form by its handle.
     *
     * @param string $handle
     *
     * @throws Exception
     *
     * @return Form
     */
    public function getFormByHandle($handle)
    {
        $form = $this->getQuery()->handle($handle)->one();

        if (!$form || !$form instanceof Form) {
            throw new Exception(Craft::t('simple-forms', 'No form exists with the handle “{handle}”.', ['handle' => $handle]));
        }

        return $form;
    }

    /**
     * Save a form.
     *
     * @param Form $form
     *
     * @throws Exception
     * @throws \Throwable
     *
     * @return bool
     */
    public function saveForm(Form $form)
    {
        // Is submissions or notifications enabled?
        if (!$form->submissionEnabled && !$form->notificationEnabled) {
            $form->addError('submissionEnabled', Craft::t('simple-forms', 'Submissions or notifications must be enabled, otherwise you will lose the submission.'));
            $form->addError('notificationEnabled', Craft::t('simple-forms', 'Notifications or submissions must be enabled, otherwise you will lose the submission.'));
        }

        if (!$form->hasErrors()) {
            // Save the element!
            if (Craft::$app->getElements()->saveElement($form)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get unique name and handle for a form.
     *
     * @param Form $form
     *
     * @throws Exception
     */
    public function getUniqueNameAndHandle(Form $form)
    {
        $slugWordSeparator = Craft::$app->getConfig()->getGeneral()->slugWordSeparator;
        $maxSlugIncrement = Craft::$app->getConfig()->getGeneral()->maxSlugIncrement;

        for ($i = 0; $i < $maxSlugIncrement; $i++) {
            $testName = $form->name;

            if ($i > 0) {
                $testName .= $slugWordSeparator.$i;
            }

            $originalName = $form->name;
            $originalHandle = $form->handle;
            $form->name = $testName;
            $form->handle = StringHelper::toCamelCase($form->name);

            $totalForms = (int) (new Query())
                ->select('count(id)')
                ->from('amforms_forms')
                ->where('name=:name AND handle=:handle', [
                    ':name'   => $form->name,
                    ':handle' => $form->handle,
                ])
                ->scalar() ?? 0;

            if ($totalForms === 0) {
                return;
            } else {
                $form->name = $originalName;
                $form->handle = $originalHandle;
            }
        }

        throw new Exception(Craft::t('simple-forms', 'Could not find a unique name and handle for this form.'));
    }

    /**
     * Get a namespace for a form.
     *
     * @param Form $form
     * @param bool $createNewOnEmpty
     *
     * @return string
     */
    public function getNamespaceForForm(Form $form, $createNewOnEmpty = true)
    {
        if (!isset($this->_namespaces[$form->id]) && $createNewOnEmpty) {
            $this->_namespaces[$form->id] = 'form_'.StringHelper::randomString(10);
        }

        return isset($this->_namespaces[$form->id]) ? $this->_namespaces[$form->id] : '';
    }

    /**
     * Display a field.
     *
     * @param Form   $form
     * @param string $handle
     *
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     *
     * @return string
     */
    public function displayField(Form $form, $handle)
    {
        // Get submission model
        $submission = SimpleForms::$plugin->submissions->getActiveSubmission($form);

        // Set namespace, if one was set
        $namespace = $this->getNamespaceForForm($form, false);
        if ($namespace) {
            Craft::$app->getView()->setNamespace($namespace);
        }

        // Get template path
        $fieldTemplateInfo = SimpleForms::$plugin->simpleForms->getDisplayTemplateInfo('field', $form->fieldTemplate);

        // Get the current templates path so we can restore it at the end of this function
        $siteTemplatesPath = Craft::$app->getView()->getTemplatesPath();
        $pluginTemplatePath = SimpleForms::$plugin->getBasePath().'/templates/_display/templates/';

        // Do we have the current form fields?
        if (!isset($this->_fields[$form->id])) {
            $this->_fields[$form->id] = [];
            $supportedFields = SimpleForms::$supportedFields;

            // Get tabs
            foreach ($form->getFieldLayout()->getTabs() as $tab) {
                // Get tab's fields
                /** @var Field $field */
                foreach ($tab->getFields() as $field) {
                    // Get actual field
                    if (!in_array(get_class($field), $supportedFields)) {
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
                        'namespace' => $namespace,
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
            return;
        }
    }

    /**
     * Display a form.
     *
     * @param Form $form
     *
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     *
     * @return Twig_Markup
     */
    public function displayForm(Form $form): Twig_Markup
    {
        // Get submission model
        $submission = SimpleForms::$plugin->submissions->getActiveSubmission($form);

        // Set namespace
        $namespace = 'form_'.StringHelper::randomString(10);
        Craft::$app->getView()->setNamespace($namespace);

        // Build field HTML
        $tabs = [];
        $supportedFields = SimpleForms::$supportedFields;
        $fieldTemplateInfo = SimpleForms::$plugin->simpleForms->getDisplayTemplateInfo('field', $form->fieldTemplate);

        // Get the current templates path so we can restore it at the end of this function
        $siteTemplatesPath = Craft::$app->getView()->getTemplatesPath();
        $pluginTemplatePath = SimpleForms::$plugin->getBasePath().'/templates/_display/templates/';

        foreach ($form->getFieldLayout()->getTabs() as $tab) {
            // Tab information
            $tabs[$tab->id] = [
                'info'   => $tab,
                'fields' => [],
            ];

            // Tab fields
            $fields = $tab->getFields();
            /** @var Field $field */
            foreach ($fields as $field) {
                // Get actual field
                if (!in_array(get_class($field), $supportedFields)) {
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
                    'namespace' => $namespace,
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
            'element' => $submission,
        ];
        $bodyHtml = SimpleForms::$plugin->simpleForms->renderDisplayTemplate('tab', $form->tabTemplate, $variables);

        // Use AntiSpam?
        $antispamHtml = SimpleForms::$plugin->antiSpam->render();

        // Use reCAPTCHA?
        $recaptchaHtml = SimpleForms::$plugin->recaptcha->render();

        // Build our complete form
        $variables = [
            'form'      => $form,
            'body'      => $bodyHtml,
            'antispam'  => $antispamHtml,
            'recaptcha' => $recaptchaHtml,
            'element'   => $submission,
            'namespace' => $namespace,
        ];
        $formHtml = SimpleForms::$plugin->simpleForms->renderDisplayTemplate('form', $form->formTemplate, $variables);

        // Reset namespace
        Craft::$app->getView()->setNamespace(null);

        // Parse form
        return new Twig_Markup($formHtml, Craft::$app->getView()->getTwig()->getCharset());
    }
}
