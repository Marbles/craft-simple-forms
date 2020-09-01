<?php

namespace rias\simpleforms\controllers;

use Craft;
use craft\db\Query;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use DateTime;
use Exception;
use rias\simpleforms\elements\Form;
use rias\simpleforms\elements\Submission;
use rias\simpleforms\SimpleForms;
use yii\web\HttpException;

/**
 * simple-forms - Submissions controller.
 */
class SubmissionsController extends Controller
{
    protected $allowAnonymous = ['save-submission', 'action-clean-up'];

    /**
     * Show submissions.
     */
    public function actionIndex()
    {
        $variables = [
            'elementType' => Submission::class,
        ];

        return $this->renderTemplate('simple-forms/submissions/index', $variables);
    }

    /**
     * Edit a submission.
     *
     * @param int|null        $submissionId
     * @param Submission|null $submission
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionEditSubmission(int $submissionId = null, Submission $submission = null)
    {
        // Do we have a submission model?
        if (!$submission) {
            // We require a submission ID
            if ($submissionId === null) {
                throw new HttpException(404);
            }

            // Get submission if available
            $submission = SimpleForms::$plugin->submissions->getSubmissionById($submissionId);
        }

        Craft::$app->getContent()->populateElementContent($submission);

        // Get form if available
        $form = SimpleForms::$plugin->forms->getFormById($submission->formId);

        // Get tabs
        $tabs = [];
        $layoutTabs = $submission->getFieldLayout()->getTabs();
        foreach ($layoutTabs as $tab) {
            $tabs[$tab->id] = [
                'label' => $tab->name,
                'url'   => '#'.$tab->getHtmlId(),
            ];
        }

        // Add notes to tabs
        $tabs['notes'] = [
            'label' => Craft::t('simple-forms', 'Notes'),
            'url'   => $submission->getCpEditUrl().'/notes',
        ];

        // Set variables
        $variables = [];
        $variables['submissionId'] = $submissionId;
        $variables['submission'] = $submission;
        $variables['form'] = $form;
        $variables['tabs'] = $tabs;
        $variables['layoutTabs'] = $layoutTabs;

        $this->renderTemplate('simple-forms/submissions/_edit', $variables);
    }

    /**
     * Save a form submission.
     *
     * @throws Exception
     * @throws \Throwable
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSaveSubmission()
    {
        $this->requirePostRequest();

        // Get the form
        $handle = Craft::$app->getRequest()->getRequiredBodyParam('handle');
        /** @var Form $form */
        $form = SimpleForms::$plugin->forms->getFormByHandle($handle);

        // Get namespace
        $namespace = Craft::$app->getRequest()->getBodyParam('namespace');

        // Get the submission? Are we editing one?
        $submissionId = (int) Craft::$app->getRequest()->getBodyParam('submissionId');

        // Get the submission
        $submission = new Submission();
        if ($submissionId) {
            $submission = SimpleForms::$plugin->submissions->getSubmissionById($submissionId);
        }

        // Front-end submission, trigger AntiSpam or reCAPTCHA?
        if (!Craft::$app->getRequest()->getIsCpRequest()) {
            // Where was this submission submitted?
            $submission->submittedFrom = urldecode(Craft::$app->getRequest()->getReferrer());

            // Validate AntiSpam settings
            $submission->spamFree = SimpleForms::$plugin->antiSpam->verify($form->handle);

            // Redirect our spammers before reCAPTCHA can be triggered
            if (!$submission->spamFree) {
                return $this->_doRedirect($submission, false);
            } else {
                SimpleForms::$plugin->antiSpam->setMarkedAsNoSpam($form->handle);
            }

            // Validate reCAPTCHA
            if (SimpleForms::$plugin->getSettings()->googleRecaptchaEnabled) {
                $submission->spamFree = SimpleForms::$plugin->recaptcha->verify();

                // Was it verified?
                if (!$submission->spamFree) {
                    $submission->addError('spamFree', Craft::t('simple-forms', 'reCAPTCHA was not verified.'));

                    // Don't upload files now
                    if (count($_FILES)) {
                        foreach ($_FILES as $key => $file) {
                            unset($_FILES[$key]);
                        }
                    }

                    return $this->_doRedirect($submission, false);
                }
            }
        } else {
            // Possible user author?
            $authorId = Craft::$app->getRequest()->getBodyParam('authorId');
            if (is_array($authorId)) {
                $authorId = current($authorId);
            }
            $submission->authorId = $authorId;
        }

        // Add the form to the submission and populate it
        Craft::$app->getContent()->populateElementContent($submission);
        $submission->form = $form;
        $submission->formId = $form->id;

        // Set attributes
        $submission->ipAddress = Craft::$app->getRequest()->getUserHost();
        $submission->userAgent = Craft::$app->getRequest()->getUserAgent();

        // Save field values from request
        $request = Craft::$app->getRequest();
        $fieldsLocation = $namespace ?: (string) $request->getParam('fieldsLocation', 'fields');
        $submission->setFieldValuesFromRequest($fieldsLocation);

        // Save submission
        if (SimpleForms::$plugin->submissions->saveSubmission($submission)) {
            // Remove spam free token
            SimpleForms::$plugin->antiSpam->verify($form->handle);

            // Notification for new submissions
            if (!Craft::$app->getRequest()->getIsCpRequest() && !$submissionId) {
                SimpleForms::$plugin->submissions->emailSubmission($submission);
            }

            // Redirect
            if (Craft::$app->getRequest()->getIsAjax()) {
                $afterSubmitText = $form->afterSubmitText ? $form->afterSubmitText : Craft::t('simple-forms', 'Thanks for your submission.');

                return $this->asJson([
                    'success'         => true,
                    'afterSubmitText' => $afterSubmitText,
                ]);
            } elseif (Craft::$app->getRequest()->getIsCpRequest()) {
                Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Submission saved.'));

                return $this->redirectToPostedUrl($submission);
            } else {
                return $this->_doRedirect($submission, true);
            }
        } else {
            if (Craft::$app->getRequest()->getIsAjax()) {
                return $this->asJson([
                    'success' => false,
                    'errors'  => $submission->getErrors(),
                ]);
            } elseif (Craft::$app->getRequest()->getIsCpRequest()) {
                Craft::$app->getSession()->setError(Craft::t('simple-forms', 'Couldn’t save submission.'));

                // Send the submission back to the template
                return Craft::$app->getUrlManager()->setRouteParams([
                    'submission' => $submission,
                ]);
            } else {
                // Remember active submissions
                SimpleForms::$plugin->submissions->setActiveSubmission($submission);

                // Return the submission by the form's handle, for custom HTML possibilities
                return Craft::$app->getUrlManager()->setRouteParams([
                    $form->handle => $submission,
                ]);
            }
        }
    }

    /**
     * Delete a submission.
     *
     * @throws Exception
     * @throws HttpException
     * @throws \Throwable
     */
    public function actionDeleteSubmission()
    {
        $this->requirePostRequest();

        // Get the submission
        $submissionId = Craft::$app->getRequest()->getRequiredBodyParam('submissionId');
        $submission = SimpleForms::$plugin->submissions->getSubmissionById($submissionId);

        // Delete submission
        if (SimpleForms::$plugin->submissions->deleteSubmission($submission)) {
            return $this->redirectToPostedUrl($submission);
        }

        throw new Exception(Craft::t('simple-forms', 'Something went wrong while deleting the submission'));
    }

    /**
     * Clean up submissions.
     *
     * @throws \Throwable
     */
    public function actionCleanUp()
    {
        $success = false;

        // Can we clean up?
        $cleanUp = (bool) SimpleForms::$plugin->getSettings()->cleanUpSubmissions;
        if ($cleanUp) {
            // From when do we have to clean up?
            $cleanUpFrom = SimpleForms::$plugin->getSettings()->cleanUpSubmissionsFrom;

            // Get accurate date (by UTC)
            $now = new DateTime();
            $cleanUpFromDate = $now->modify($cleanUpFrom);

            // Get submissions
            $submissionIds = (new Query())
                ->select('id')
                ->from('{{%simple-forms_submissions}}')
                ->where('dateCreated <= "'.$cleanUpFromDate->format('Y-m-d H:i:s').'"')
                ->column();

            // Delete them!
            foreach ($submissionIds as $submissionId) {
                $success = Craft::$app->getElements()->deleteElementById($submissionId);
            }
        }

        return $this->asJson([
            'success' => $success,
        ]);
    }

    /**
     * Do redirect with {placeholders} support.
     *
     * @param Submission $submission
     * @param bool       $submitted
     *
     * @throws Exception
     * @throws \yii\web\BadRequestHttpException
     */
    private function _doRedirect(Submission $submission, $submitted)
    {
        $vars = array_merge(
            [
                'siteUrl'   => UrlHelper::siteUrl(),
                'submitted' => $submitted,
            ],
            $submission->getAttributes()
        );

        $url = null;
        $redirectUrl = $submission->getForm()->getRedirectUrl();
        if (empty($redirectUrl)) {
            $url = Craft::$app->getRequest()->getFullPath().'?submitted='.($submitted ? $submission->getForm()->handle : 0);
        }

        $this->redirectToPostedUrl($vars, $url);
    }
}
