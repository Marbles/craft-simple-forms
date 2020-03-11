<?php
namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\Field;
use craft\elements\Asset;
use craft\fields\Assets;
use craft\helpers\ArrayHelper;
use craft\mail\Message;
use rias\simpleforms\elements\db\SubmissionsQuery;
use rias\simpleforms\elements\Form;
use rias\simpleforms\elements\Submission;
use rias\simpleforms\events\EmailSubmissionEvent;
use rias\simpleforms\events\SubmissionEvent;
use rias\simpleforms\SimpleForms;

use craft\fields\Date;

/**
 * simple-forms - Submissions service
 */
class SubmissionsService extends Component
{
    const EVENT_BEFORE_SAVE_SUBMISSION = 'beforeSaveSubmission';
    const EVENT_AFTER_SAVE_SUBMISSION = 'afterSaveSubmission';
    const EVENT_BEFORE_DELETE_SUBMISSION = 'beforeDeleteSubmission';
    const EVENT_AFTER_DELETE_SUBMISSION = 'afterDeleteSubmission';
    const EVENT_BEFORE_EMAIL_SUBMISSION = 'beforeEmailSubmission';
    const EVENT_AFTER_EMAIL_SUBMISSION = 'afterEmailSubmission';
    const EVENT_BEFORE_EMAIL_CONFIRMATION_SUBMISSION = 'beforeEmailConfirmationSubmission';
    const EVENT_AFTER_EMAIL_CONFIRMATION_SUBMISSION = 'afterEmailConfirmationSubmission';

    private $_activeSubmissions = array();

    /**
     * Returns a criteria model for AmForms_Submission elements.
     *
     * @param array $config
     * @return SubmissionsQuery
     */
    public function getCriteria(array $config = [])
    {
        return new SubmissionsQuery(Submission::class, $config);
    }

    /**
     * Get all submissions.
     *
     * @return Submission|array|null
     */
    public function getAllSubmissions()
    {
        return $this->getCriteria(['orderBy' => 'name'])->all();
    }

    /**
     * Get a submission by its ID.
     *
     * @param int $id
     *
     * @return array|\craft\base\ElementInterface|null|Submission
     */
    public function getSubmissionById($id)
    {
        $submission = $this->getCriteria(['id' => $id])->one();
        Craft::$app->getContent()->populateElementContent($submission);
        return $submission;
    }

    /**
     * Set an active front-end submission.
     *
     * @param Submission $submission
     * @throws \Exception
     */
    public function setActiveSubmission(Submission $submission)
    {
        $this->_activeSubmissions[$submission->getForm()->handle] = $submission;
    }

    /**
     * Get an active front-end submission based on a form.
     *
     * @param Form $form
     *
     * @return Submission
     */
    public function getActiveSubmission(Form $form)
    {
        if (isset($this->_activeSubmissions[$form->handle])) {
            return $this->_activeSubmissions[$form->handle];
        }

        return new Submission(['formId' => $form->id]);
    }

    /**
     * Save a submission.
     *
     * @param Submission $submission
     *
     * @return bool
     * @throws \Exception
     * @throws \Throwable
     */
    public function saveSubmission(Submission $submission)
    {
        $isNewSubmission = ! $submission->id;

        // If we don't need to save it, return a success for other events
        if ($isNewSubmission && ! $submission->form->submissionEnabled) {
            return true;
        }

        $event = new SubmissionEvent([
            'submission' => $submission,
            'isNew' => $isNewSubmission,
        ]);
        $this->trigger(self::EVENT_BEFORE_SAVE_SUBMISSION, $event);

        $submission->title = Craft::$app->getView()->renderObjectTemplate($submission->form->titleFormat, $submission);
        if (Craft::$app->getElements()->saveElement($submission)) {
            $event = new SubmissionEvent([
                'submission' => $submission,
                'isNew' => $isNewSubmission,
            ]);
            $this->trigger(self::EVENT_AFTER_SAVE_SUBMISSION, $event);

            return true;
        }

        return false;
    }

    /**
     * Delete a submission.
     *
     * @param Submission $submission
     *
     * @return bool
     * @throws \Throwable
     */
    public function deleteSubmission(Submission $submission)
    {
        $event = new SubmissionEvent([
            'submission' => $submission,
        ]);
        $this->trigger(self::EVENT_BEFORE_DELETE_SUBMISSION, $event);

        // Is the event giving us the go-ahead?
        if ($event->isValid) {
            if (Craft::$app->getElements()->deleteElement($submission)) {
                // Fire an 'onDeleteSubmission' event
                $event = new SubmissionEvent([
                    'submission' => $submission,
                ]);
                $this->trigger(self::EVENT_AFTER_DELETE_SUBMISSION, $event);

                return true;
            }
        }

        return false;
    }

    /**
     * Email a submission.
     *
     * @param Submission $submission
     * @param mixed $overrideRecipients [Optional] Override recipients from form settings.
     *
     * @return bool
     * @throws \Exception
     * @throws \Throwable
     */
    public function emailSubmission(Submission $submission, $overrideRecipients = false)
    {
        // Do we even have a form ID?
        if (! $submission->formId) {
            return false;
        }

        // Get form if not already set
        $submission->getForm();
        $form = $submission->form;
        $submission->formName = $form->name;
        if (! $form->notificationEnabled) {
            return false;
        }

        // Get our recipients
        $recipients = explode(',', $this->_translatedObjectPlusEnvironment($form->notificationRecipients, $submission));
        if ($overrideRecipients !== false) {
            if (is_array($overrideRecipients) && count($overrideRecipients)) {
                $recipients = $overrideRecipients;
            }
            elseif (is_string($overrideRecipients)) {
                $recipients = explode(',', $overrideRecipients);
            }
        }
        $recipients = array_unique($recipients);
        array_walk($recipients, 'trim');
        if (! count($recipients)) {
            return false;
        }

        // Other email attributes
        $replyTo = null;
        if ($form->notificationReplyToEmail) {
            $replyTo = $this->_translatedObjectPlusEnvironment($form->notificationReplyToEmail, $submission);
            if (! filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $replyTo = null;
            }
        }

        // Start mailing!
        $success = false;

        // Notification email
        $notificationEmail = new Message();
        $notificationEmail->setHtmlBody($this->getSubmissionEmailBody($submission));
        $notificationEmail->setFrom([
            $this->_translatedObjectPlusEnvironment($form->notificationSenderEmail, $submission) =>
            $this->_translatedObjectPlusEnvironment($form->notificationSenderName, $submission)
        ]);
        if (trim($form->notificationSubject) != '') {
            $notificationEmail->setSubject($this->_translatedObjectPlusEnvironment($form->notificationSubject, $submission));
        } else {
            $notificationEmail->setSubject($this->_translatedObjectPlusEnvironment('{formName} form was submitted', $submission));
        }
        if ($replyTo) {
            $notificationEmail->setReplyTo($replyTo);
        }

        // Confirmation email
        $confirmationEmail = new Message();
        $confirmationEmail->setHtmlBody($this->getConfirmationEmailBody($submission));
        $confirmationEmail->setFrom([
            $this->_translatedObjectPlusEnvironment($form->confirmationSenderEmail, $submission) =>
            $this->_translatedObjectPlusEnvironment($form->confirmationSenderName, $submission)
        ]);
        if (trim($form->confirmationSubject) != '') {
            $confirmationEmail->setSubject($this->_translatedObjectPlusEnvironment($form->confirmationSubject, $submission));
        } else {
            $confirmationEmail->setSubject($this->_translatedObjectPlusEnvironment('Thanks for your submission.', $submission));
        }

        // Add Bcc?
        $bccEmailAddress = SimpleForms::$plugin->getSettings()->bccEmailAddress;
        if ($bccEmailAddress) {
            $bccAddresses = explode(',', $bccEmailAddress);
            $bccAddresses = array_unique($bccAddresses);
            array_walk($bccAddresses, 'trim');

            if (count($bccAddresses)) {
                $properBccAddresses = [];

                foreach ($bccAddresses as $bccAddress) {
                    $bccAddress = $this->_translatedObjectPlusEnvironment($bccAddress, $submission);

                    if (filter_var($bccAddress, FILTER_VALIDATE_EMAIL)) {
                        $properBccAddresses[] = [
                            'email' => $bccAddress
                        ];
                    }
                }

                if (count($properBccAddresses)) {
                    $notificationEmail->setBcc($properBccAddresses);
                    $confirmationEmail->setBcc($properBccAddresses);
                }
            }
        }

        // Add files to the notification?
        if ($form->notificationFilesEnabled) {
            foreach ($submission->getFieldLayout()->getTabs() as $tab) {
                // Tab fields
                $fields = $tab->getFields();
                /** @var Field $field */
                foreach ($fields as $field) {
                    // Find assets
                    if (get_class($field) == Assets::class) {
                        /** @var Asset $asset */
                        foreach ($submission->{$field->handle}->all() as $asset) {
                            $file = @file_get_contents($asset->url);

                            // Add asset as attachment
                            if ($file) {
                                $notificationEmail->attachContent($file, ['fileName' => $asset->filename]);
                            }
                        }
                    }
                }
            }
        }

        $event = new EmailSubmissionEvent([
            'submission' => $submission,
            'email' => $notificationEmail,
        ]);
        $this->trigger(self::EVENT_BEFORE_EMAIL_SUBMISSION, $event);

        // Is the event giving us the go-ahead?
        if ($event->isValid) {
            // Send emails
            foreach ($recipients as $recipient) {
                $notificationEmail->setTo($this->_translatedObjectPlusEnvironment($recipient, $submission));

                if (filter_var(key($notificationEmail->getTo()), FILTER_VALIDATE_EMAIL)) {
                    if (Craft::$app->getMailer()->send($notificationEmail)) {
                        $success = true;
                    }
                }
            }

            // Fire an 'onEmailSubmission' event
            $event = new EmailSubmissionEvent([
                'submission' => $submission,
                'email' => $notificationEmail,
                'success' => $success,
            ]);
            $this->trigger(self::EVENT_AFTER_EMAIL_SUBMISSION, $event);
        }

        // Send copy?
        if ($form->sendCopy) {
            $event = new EmailSubmissionEvent([
                'submission' => $submission,
                'email' => $confirmationEmail,
            ]);
            $this->trigger(self::EVENT_BEFORE_EMAIL_CONFIRMATION_SUBMISSION, $event);

            // Is the event giving us the go-ahead?
            if ($event->isValid) {
                // Send confirmation email
                $sendCopyTo = $submission->{$form->sendCopyTo};

                if ($sendCopyTo && filter_var($sendCopyTo, FILTER_VALIDATE_EMAIL)) {
                    $confirmationEmail->setTo($this->_translatedObjectPlusEnvironment($sendCopyTo, $submission));

                    if (Craft::$app->getMailer()->send($confirmationEmail)) {
                        $success = true;
                    }
                }

                $event = new EmailSubmissionEvent([
                    'submission' => $submission,
                    'email' => $confirmationEmail,
                    'success' => $success,
                ]);
                $this->trigger(self::EVENT_AFTER_EMAIL_CONFIRMATION_SUBMISSION, $event);
            }
        }

        return $success;
    }

    /**
     * Get submission email body.
     *
     * @param Submission $submission
     *
     * @return string
     * @throws \Exception
     */
    public function getSubmissionEmailBody(Submission $submission)
    {
        // Get form if not already set
        $submission->getForm();
        /** @var Form $form */
        $form = $submission->form;

        // Get email body
        $variables = [
            'tabs' => $form->getFieldLayout()->getTabs(),
            'form' => $form,
            'submission' => $submission
        ];

        return SimpleForms::$plugin->simpleFormsService->renderDisplayTemplate('notification', $form->notificationTemplate, $variables);
    }

    /**
     * Get confirmation email body.
     *
     * @param Submission $submission
     *
     * @return string
     * @throws \Exception
     */
    public function getConfirmationEmailBody(Submission $submission)
    {
        // Get form if not already set
        $submission->getForm();
        /** @var Form $form */
        $form = $submission->form;

        // Get email body
        $variables = [
            'tabs' => $form->getFieldLayout()->getTabs(),
            'form' => $form,
            'submission' => $submission
        ];

        $overrideTemplate = $form->confirmationTemplate;
        if (empty($overrideTemplate)) {
            $overrideTemplate = $form->notificationTemplate;
        }

        return SimpleForms::$plugin->simpleFormsService->renderDisplayTemplate('confirmation', $overrideTemplate, $variables);
    }

    /**
     * Parse a string through an object and environment variables.
     *
     * @param string $string
     * @param mixed $object
     *
     * @return string
     * @throws \Throwable
     * @throws \yii\base\Exception
     */
    private function _translatedObjectPlusEnvironment($string, $object = null)
    {
        // Parse through object
        if ($object) {
            $string = Craft::$app->getView()->renderObjectTemplate($string, $object);
        }

        // Return translated string
        return Craft::t('site', $string);
    }
}
