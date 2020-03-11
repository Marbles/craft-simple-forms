<?php
namespace rias\simpleforms\variables;

use Craft;
use craft\base\Field;
use craft\base\FieldInterface;
use rias\simpleforms\SimpleForms;

class SimpleFormsVariable
{
    /**
     * Get the Plugin's name.
     *
     * @example {{ craft.simpleForms.name }}
     * @return string
     */
    public function getName()
    {
        return SimpleForms::$plugin->name;
    }

    /**
     * Get a setting value by their handle and type.
     *
     * @param string $handle
     * @param string $type
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    public function getSettingValue($handle, $type, $defaultValue = null)
    {
        return craft()->amForms_settings->getSettingValue($handle, $type, $defaultValue);
    }

    /**
     * Get a setting value by their handle and type.
     *
     * @param string $handle
     * @param string $type
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    public function getSettingsValueByHandleAndType($handle, $type, $defaultValue = null)
    {
        return $this->getSettingValue($handle, $type, $defaultValue);
    }


    // Field methods
    // =========================================================================

    /**
     * Get proper field types.
     *
     * @param array $fieldTypes All Craft's fieldtypes.
     *
     * @return array
     */
    public function getProperFieldTypes($fieldTypes)
    {
        return SimpleForms::$plugin->fieldsService->getProperFieldTypes($fieldTypes);
    }

    /**
     * Get field handles.
     *
     * @return array
     */
    public function getFieldHandles()
    {
        $handles = array();
        /** @var Field[] $fields */
        $fields = Craft::$app->getFields()->getAllFields('simple-forms');
        foreach ($fields as $field) {
            $handles[] = array('label' => $field->name, 'value' => $field->handle);
        }

        return $handles;
    }


    // Submission methods
    // =========================================================================

    /**
     * Returns a criteria model for AmForms_Submission elements.
     *
     * @param array $attributes
     *
     * @return ElementCriteriaModel
     */
    public function submissions($attributes = array())
    {
        return craft()->amForms_submissions->getCriteria($attributes);
    }

    /**
     * Get a submission by its ID.
     *
     * @param int $id
     * @param bool $setAsActive [Optional] Set as active submission, for editing purposes.
     *
     */
    public function getSubmissionById($id, $setAsActive = false)
    {
        if ($setAsActive) {
            // Get the submission
            $submission = $this->getSubmissionById($id);
            if (! $submission) {
                SimpleForms::$plugin->simpleFormsService->handleError(Craft::t('simple-forms', 'No submission exists with the ID “{id}”.', ['id' => $id]));
                return false;
            }

            // Get the form
            $form = $submission->getForm();
            if (! $form) {
                SimpleForms::$plugin->simpleFormsService->handleError(Craft::t('simple-forms', 'No form exists with the ID “{id}”.', ['id' => $submission->formId]));
                return false;
            }

            // Set active submission
            SimpleForms::$plugin->submissionsService->setActiveSubmission($submission);

            return $submission;
        }
        return SimpleForms::$plugin->submissionsService->getSubmissionById($id);
    }

    /**
     * Display a submission to edit and save it.
     *
     * @param int $id
     *
     * @return string
     */
    public function displaySubmission($id)
    {
        // Get the submission
        $submission = $this->getSubmissionById($id);
        if (! $submission) {
            SimpleForms::$plugin->simpleFormsService->handleError(Craft::t('simple-forms', 'No submission exists with the ID “{id}”.', ['id' => $id]));
            return false;
        }

        // Get the form
        $form = $submission->getForm();
        if (! $form) {
            SimpleForms::$plugin->simpleFormsService->handleError(Craft::t('simple-forms', 'No form exists with the ID “{id}”.', ['id' => $submission->formId]));
            return false;
        }

        // Set active submission
        SimpleForms::$plugin->submissionsService->setActiveSubmission($submission);

        // Display the edit form!
        return SimpleForms::$plugin->formsService->displayForm($form);
    }


    // Form methods
    // =========================================================================

    /**
     * Get a form by its ID.
     *
     * @param int $id
     *
     * @return AmForms_FormModel|null
     */
    public function getFormById($id)
    {
        return SimpleForms::$plugin->formsService->getFormById($id);
    }

    /**
     * Get a form by its handle.
     *
     * @param string $handle
     *
     * @return AmForms_FormModel|null
     */
    public function getFormByHandle($handle)
    {
        return SimpleForms::$plugin->formsService->getFormByHandle($handle);
    }

    /**
     * Get all forms.
     *
     * @return array
     */
    public function getAllForms()
    {
        return SimpleForms::$plugin->formsService->getAllForms();
    }

    /**
     * Get a namespace for a form.
     *
     * @param AmForms_FormModel $form
     *
     * @return string
     */
    public function getNamespaceForForm(AmForms_FormModel $form)
    {
        return SimpleForms::$plugin->formsService->getNamespaceForForm($form);
    }

    /**
     * Get a form by its handle.
     *
     * @param string $handle
     *
     * @return AmForms_FormModel|bool
     */
    public function getForm($handle)
    {
        // Get the form
        $form = $this->getFormByHandle($handle);
        if (! $form) {
            SimpleForms::$plugin->simpleFormsService->handleError(Craft::t('simple-forms', 'No form exists with the handle “{handle}”.', ['handle' => $handle]));
            return false;
        }
        return $form;
    }

    /**
     * Display a form.
     *
     * @param string $handle
     *
     * @return string
     */
    public function displayForm($handle)
    {
        // Get the form
        $form = $this->getFormByHandle($handle);
        if (! $form) {
            SimpleForms::$plugin->simpleFormsService->handleError(Craft::t('simple-forms', 'No form exists with the handle “{handle}”.', ['handle' => $handle]));
            return false;
        }
        return SimpleForms::$plugin->formsService->displayForm($form);
    }


    // Anti spam methods
    // =========================================================================

    /**
     * Display AntiSpam widget.
     *
     * @return bool|string
     */
    public function displayAntispam()
    {
        return SimpleForms::$plugin->antiSpamService->render();
    }

    /**
     * Display a reCAPTCHA widget.
     *
     * @return bool|string
     */
    public function displayRecaptcha()
    {
        return SimpleForms::$plugin->recaptchaService->render();
    }
}
