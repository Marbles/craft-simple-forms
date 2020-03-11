<?php
namespace rias\simpleforms\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use Exception;
use rias\simpleforms\elements\Form;
use rias\simpleforms\elements\Submission;
use rias\simpleforms\SimpleForms;
use yii\web\HttpException;

/**
 * simple-forms - Forms controller
 */
class FormsController extends Controller
{
    /**
     * Make sure the current has access.
     *
     * @param $id
     * @param $module
     * @throws HttpException
     */
    public function __construct($id, $module)
    {
        parent::__construct($id, $module);

        $user = Craft::$app->getUser()->getIdentity();
        if (! $user->can('accessAmFormsForms')) {
            throw new HttpException(403, Craft::t('simple-forms', 'This action may only be performed by users with the proper permissions.'));
        }
    }

    /**
     * Show forms.
     */
    public function actionIndex()
    {
        $variables = [
            'elementType' => Form::class,
        ];

        return $this->renderTemplate('simple-forms/forms/index', $variables);
    }

    /**
     * Create or edit a form.
     *
     * @param int|null $formId
     * @param Form|null $form
     * @throws Exception
     */
    public function actionEditForm(int $formId = null, Form $form = null)
    {
        $variables = [
            'formId' => $formId,
        ];

        // Do we have a form model?
        if (! $form) {
            // Get form if available
            if ($formId) {
                $variables['form'] = SimpleForms::$plugin->formsService->getFormById($formId);

                if (! $variables['form']) {
                    throw new Exception(Craft::t('simple-forms', 'No form exists with the ID “{id}”.', ['id' => $formId]));
                }
            }
            else {
                $variables['form'] = new Form();
            }
        }

        // Fields per set setting
        $fieldsPerSet = SimpleForms::$plugin->getSettings()->fieldsPerSet;
        $fieldsPerSet = ($fieldsPerSet && is_numeric($fieldsPerSet)) ? (int) $fieldsPerSet : 8;

        // Get available fields with our context
        $groupId = 1;
        $counter = 1;
        $variables['groups'] = array();
        $fields = Craft::$app->getFields()->getAllFields('simple-forms');
        foreach ($fields as $field) {
            if ($counter % $fieldsPerSet == 1) {
                $groupId ++;
                $counter = 1;
            }
            $variables['groups'][$groupId]['fields'][] = $field;
            $counter ++;
        }

        // Get redirectEntryId elementType
        $variables['entryElementType'] = Entry::class;

        // Get available attributes
        $variables['availableAttributes'] = [];
        $submission = new Submission();
        $ignoreAttributes = [
            'slug', 'uri', 'root', 'lft', 'rgt', 'level', 'searchScore', 'localeEnabled', 'archived', 'spamFree'
        ];
        foreach ($submission->getAttributes() as $attribute => $value) {
            if (! in_array($attribute, $ignoreAttributes)) {
                $variables['availableAttributes'][] = $attribute;
            }
        }
        foreach ($fields as $field) {
            $variables['availableAttributes'][] = $field['handle'];
        }

        $this->renderTemplate('simple-forms/forms/_edit', $variables);
    }

    /**
     * Save a form.
     *
     * @throws \yii\web\BadRequestHttpException
     * @throws Exception
     * @throws \Throwable
     */
    public function actionSaveForm()
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $form = new Form();

        $formId = $request->getBodyParam('formId');
        if ($formId && $formId !== 'copy') {
            $form = SimpleForms::$plugin->formsService->getFormById($formId);

            if (! $form) {
                throw new Exception(Craft::t('simple-forms', 'No form exists with the ID “{id}”.', ['id' => $formId]));
            }
        }

        if ($form->fieldLayoutId) {
            Craft::$app->getFields()->deleteLayoutById($form->fieldLayoutId);
        }

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Form::class;
        Craft::$app->getFields()->saveLayout($fieldLayout);
        $form->fieldLayoutId = $fieldLayout->id;

        // Get redirectEntryId
        $redirectEntryId = $request->getBodyParam('redirectEntryId');

        // Form attributes
        $form->redirectEntryId          = $redirectEntryId && is_array($redirectEntryId) && count($redirectEntryId) ? $redirectEntryId[0] : null;
        $form->name                     = $request->getBodyParam('name');
        $form->handle                   = $request->getBodyParam('handle');
        $form->titleFormat              = $request->getBodyParam('titleFormat');
        $form->submitAction             = $request->getBodyParam('submitAction');
        $form->submitButton             = $request->getBodyParam('submitButton');
        $form->afterSubmit              = $request->getBodyParam('afterSubmit');
        $form->afterSubmitText          = $request->getBodyParam('afterSubmitText');
        $form->submissionEnabled        = $request->getBodyParam('submissionEnabled');
        $form->displayTabTitles         = $request->getBodyParam('displayTabTitles');
        $form->redirectUrl              = $request->getBodyParam('redirectUrl');
        $form->sendCopy                 = $request->getBodyParam('sendCopy');
        $form->sendCopyTo               = $request->getBodyParam('sendCopyTo');
        $form->notificationEnabled      = $request->getBodyParam('notificationEnabled');
        $form->notificationFilesEnabled = $request->getBodyParam('notificationFilesEnabled');
        $form->notificationRecipients   = $request->getBodyParam('notificationRecipients');
        $form->notificationSubject      = $request->getBodyParam('notificationSubject');
        $form->confirmationSubject      = $request->getBodyParam('confirmationSubject');
        $form->notificationSenderName   = $request->getBodyParam('notificationSenderName');
        $form->confirmationSenderName   = $request->getBodyParam('confirmationSenderName');
        $form->notificationSenderEmail  = $request->getBodyParam('notificationSenderEmail');
        $form->confirmationSenderEmail  = $request->getBodyParam('confirmationSenderEmail');
        $form->notificationReplyToEmail = $request->getBodyParam('notificationReplyToEmail');
        $form->formTemplate             = $request->getBodyParam('formTemplate', $form->formTemplate);
        $form->tabTemplate              = $request->getBodyParam('tabTemplate', $form->tabTemplate);
        $form->fieldTemplate            = $request->getBodyParam('fieldTemplate', $form->fieldTemplate);
        $form->notificationTemplate     = $request->getBodyParam('notificationTemplate', $form->notificationTemplate);
        $form->confirmationTemplate     = $request->getBodyParam('confirmationTemplate', $form->confirmationTemplate);

        // Duplicate form, so the name and handle are taken
        if ($formId && $formId === 'copy') {
            SimpleForms::$plugin->formsService->getUniqueNameAndHandle($form);
        }

        // Save form
        if (SimpleForms::$plugin->formsService->saveForm($form)) {
            Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Form saved.'));

            $this->redirectToPostedUrl($form);
        } else {
            Craft::$app->getSession()->setError(Craft::t('simple-forms', 'Couldn’t save form.'));

            // Send the form back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'form' => $form
            ]);
        }
    }

    /**
     * Delete a form.
     */
    public function actionDeleteForm()
    {
        $this->requirePostRequest();

        // Get form if available
        $formId = craft()->request->getRequiredPost('formId');
        $form = SimpleForms::$plugin->formsService->getFormById($formId);
        if (! $form) {
            throw new Exception(Craft::t('No form exists with the ID “{id}”.', array('id' => $formId)));
        }

        // Delete form
        if (SimpleForms::$plugin->formsService->deleteForm($form)) {
            craft()->userSession->setNotice(Craft::t('Form deleted.'));
        }
        else {
            craft()->userSession->setError(Craft::t('Couldn’t delete form.'));
        }

        $this->redirectToPostedUrl($form);
    }
}
