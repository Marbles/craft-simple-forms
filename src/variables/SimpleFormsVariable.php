<?php

namespace rias\simpleforms\variables;

use Craft;
use craft\base\Field;
use craft\helpers\Template;
use craft\web\View;
use rias\simpleforms\elements\Form;
use rias\simpleforms\SimpleForms;
use Twig_Markup;

class SimpleFormsVariable
{
    /**
     * @var int
     */
    private $_injected = 0;

    /**
     * Get the Plugin's name.
     *
     * @example {{ craft.simpleForms.name }}
     *
     * @return string
     */
    public function getName()
    {
        return SimpleForms::$plugin->name;
    }

    // Field methods
    // =========================================================================

    /**
     * Get field handles.
     *
     * @return array
     */
    public function getFieldHandles()
    {
        $handles = [];
        /** @var Field[] $fields */
        $fields = Craft::$app->getFields()->getAllFields('simple-forms');
        foreach ($fields as $field) {
            $handles[] = ['label' => $field->name, 'value' => $field->handle];
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
     * @return \rias\simpleforms\elements\db\SubmissionsQuery
     */
    public function submissions($attributes = [])
    {
        return SimpleForms::$plugin->submissions->getCriteria($attributes);
    }

    /**
     * Get a submission by its ID.
     *
     * @param int  $id
     * @param bool $setAsActive [Optional] Set as active submission, for editing purposes.
     *
     * @throws \Exception
     *
     * @return array|bool|\craft\base\ElementInterface|\rias\simpleforms\elements\Submission|null
     */
    public function getSubmissionById($id, $setAsActive = false)
    {
        if ($setAsActive) {
            // Get the submission
            try {
                $submission = $this->getSubmissionById($id);
            } catch (\Exception $e) {
                SimpleForms::$plugin->simpleForms->handleError(Craft::t('simple-forms', 'No submission exists with the ID “{id}”.', ['id' => $id]));

                return false;
            }

            // Set active submission
            SimpleForms::$plugin->submissions->setActiveSubmission($submission);

            return $submission;
        }

        return SimpleForms::$plugin->submissions->getSubmissionById($id);
    }

    /**
     * Display a submission to edit and save it.
     *
     * @param int $id
     *
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     * @throws \Exception
     *
     * @return Twig_Markup
     */
    public function displaySubmission($id): Twig_Markup
    {
        // Get the submission
        try {
            $submission = $this->getSubmissionById($id);
        } catch (\Exception $e) {
            SimpleForms::$plugin->simpleForms->handleError(Craft::t('simple-forms', 'No submission exists with the ID “{id}”.', ['id' => $id]));

            return new Twig_Markup('', Craft::$app->charset);
        }

        // Get the form
        try {
            $form = $submission->getForm();
        } catch (\Exception $e) {
            SimpleForms::$plugin->simpleForms->handleError(Craft::t('simple-forms', 'No form exists with the ID “{id}”.', ['id' => $submission->formId]));

            return new Twig_Markup('', Craft::$app->charset);
        }

        // Set active submission
        SimpleForms::$plugin->submissions->setActiveSubmission($submission);

        // Display the edit form!
        return SimpleForms::$plugin->forms->displayForm($form);
    }

    // Form methods
    // =========================================================================

    /**
     * Get a form by its ID.
     *
     * @param int $id
     *
     * @throws \Exception
     *
     * @return array|\craft\base\ElementInterface|\rias\simpleforms\elements\Form|null
     */
    public function getFormById($id)
    {
        try {
            return SimpleForms::$plugin->forms->getFormById($id);
        } catch (\Exception $e) {
            SimpleForms::$plugin->simpleForms->handleError(Craft::t('simple-forms', $e->getMessage()));
        }
    }

    /**
     * Get a form by its handle.
     *
     * @param string $handle
     *
     * @throws \Exception
     *
     * @return array|\craft\base\ElementInterface|null
     */
    public function getFormByHandle($handle)
    {
        try {
            return SimpleForms::$plugin->forms->getFormByHandle($handle);
        } catch (\Exception $e) {
            SimpleForms::$plugin->simpleForms->handleError(Craft::t('simple-forms', $e->getMessage()));

            return;
        }
    }

    /**
     * Get all forms.
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getAllForms()
    {
        return SimpleForms::$plugin->forms->getAllForms();
    }

    /**
     * Get a namespace for a form.
     *
     *
     * @param Form $form
     *
     * @return string
     */
    public function getNamespaceForForm(Form $form)
    {
        return SimpleForms::$plugin->forms->getNamespaceForForm($form);
    }

    /**
     * Get a form by its handle.
     *
     * @param string $handle
     *
     * @throws \Exception
     *
     * @return array|bool|\craft\base\ElementInterface|Form
     */
    public function getForm($handle)
    {
        return $this->getFormByHandle($handle);
    }

    /**
     * Display a form.
     *
     * @param string|Form $form
     *
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     * @throws \Exception
     *
     * @return Twig_Markup
     */
    public function displayForm($form): Twig_Markup
    {
        if (!$form instanceof Form) {
            // Get the form
            /** @var Form $form */
            $form = $this->getFormByHandle($form);
        }

        return SimpleForms::$plugin->forms->displayForm($form);
    }

    // Anti spam methods
    // =========================================================================

    /**
     * Display AntiSpam widget.
     *
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     *
     * @return bool|string
     */
    public function displayAntispam()
    {
        return SimpleForms::$plugin->antiSpam->render();
    }

    /**
     * Display a reCAPTCHA widget.
     *
     * @return bool|string
     */
    public function displayRecaptcha()
    {
        return SimpleForms::$plugin->recaptcha->render();
    }

    public function supportedFields()
    {
        return SimpleForms::$supportedFields;
    }

    /**
     * Gets Form Groups.
     *
     * @param int $id Group ID (optional)
     *
     * @return array
     */
    public function getAllFormGroups($id = null)
    {
        return SimpleForms::$plugin->groups->getAllFormGroups($id);
    }

    /**
     * Gets all forms in a specific group by ID.
     *
     * @param $id
     *
     * @return Form
     */
    public function getFormsByGroupId($id)
    {
        return SimpleForms::$plugin->groups->getFormsByGroupId($id);
    }

    /**
     * Returns a script to get a CSRF input field.
     *
     * @return Twig_Markup
     */
    public function csrfInput(): Twig_Markup
    {
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        if (SimpleForms::$plugin->getSettings()->useInjectedCsrfInput) {
            $uri = '/'.$generalConfig->actionTrigger.'/simple-forms/csrf/input';

            return $this->_getScript($uri);
        }

        if ($generalConfig->enableCsrfProtection === true) {
            return Template::raw('<input type="hidden" name="'.$generalConfig->csrfTokenName.'" value="'.Craft::$app->getRequest()->getCsrfToken().'">');
        }

        return Template::raw('');
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a script to inject the output of a URI into a div.
     *
     * @param string $uri
     *
     * @return Twig_Markup
     */
    private function _getScript(string $uri): Twig_Markup
    {
        $view = Craft::$app->getView();

        if ($this->_injected === 0) {
            $view->registerJs('
                function simpleFormsInject(id, uri) {
                    var xhr = new XMLHttpRequest();
                    xhr.onload = function () {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            document.getElementById("simple-forms-inject-" + id).innerHTML = this.responseText;
                        }
                    };
                    xhr.open("GET", uri);
                    xhr.send();
                }
            ', View::POS_END);
        }

        $this->_injected++;
        $id = 'simple-forms-inject-'.$this->_injected;
        $view->registerJs('simpleFormsInject('.$this->_injected.', "'.$uri.'");', View::POS_END);
        $output = '<span class="simpleForms-inject" id="'.$id.'"></span>';

        return Template::raw($output);
    }
}
