<?php

namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use craft\base\Field;
use craft\elements\Asset;
use craft\fields\Assets;
use craft\mail\Message;
use Exception;
use rias\simpleforms\elements\db\SubmissionsQuery;
use rias\simpleforms\elements\Form;
use rias\simpleforms\elements\Submission;
use rias\simpleforms\events\EmailSubmissionEvent;
use rias\simpleforms\events\SubmissionEvent;
use rias\simpleforms\SimpleForms;

/**
 * simple-forms - Submissions service.
 */
class Submissions extends Component
{
    const EVENT_BEFORE_SAVE_SUBMISSION = 'beforeSaveSubmission';
    const EVENT_AFTER_SAVE_SUBMISSION = 'afterSaveSubmission';
    const EVENT_BEFORE_DELETE_SUBMISSION = 'beforeDeleteSubmission';
    const EVENT_AFTER_DELETE_SUBMISSION = 'afterDeleteSubmission';
    const EVENT_BEFORE_EMAIL_SUBMISSION = 'beforeEmailSubmission';
    const EVENT_AFTER_EMAIL_SUBMISSION = 'afterEmailSubmission';
    const EVENT_BEFORE_EMAIL_CONFIRMATION_SUBMISSION = 'beforeEmailConfirmationSubmission';
    const EVENT_AFTER_EMAIL_CONFIRMATION_SUBMISSION = 'afterEmailConfirmationSubmission';

    private $_activeSubmissions = [];

    /**
     * Returns a criteria model for AmForms_Submission elements.
     *
     * @param array $config
     *
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
     * @throws Exception
     *
     * @return \craft\base\ElementInterface|null|array|Submission
     */
    public function getSubmissionById($id)
    {
        $submission = $this->getCriteria(['id' => $id])->one();

        if (!$submission || !$submission instanceof Submission) {
            throw new Exception(Craft::t('simple-forms', 'No submission exists with the ID “{id}”.', ['id' => $id]));
        }

        Craft::$app->getContent()->populateElementContent($submission);

        return $submission;
    }

    /**
     * Set an active front-end submission.
     *
     * @param Submission $submission
     *
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
     * @throws \Exception
     * @throws \Throwable
     *
     * @return bool
     */
    public function saveSubmission(Submission $submission)
    {
        $isNewSubmission = !$submission->id;

        // If we don't need to save it, return a success for other events
        if ($isNewSubmission && !$submission->form->submissionEnabled) {
            return true;
        }

        $this->trigger(self::EVENT_BEFORE_SAVE_SUBMISSION, new SubmissionEvent([
            'submission' => $submission,
            'isNew'      => $isNewSubmission,
        ]));

        $submission->title = Craft::$app->getView()->renderObjectTemplate($submission->form->titleFormat, $submission);
        if (Craft::$app->getElements()->saveElement($submission)) {
            $this->trigger(self::EVENT_AFTER_SAVE_SUBMISSION, new SubmissionEvent([
                'submission' => $submission,
                'isNew'      => $isNewSubmission,
            ]));

            return true;
        }

        return false;
    }

    /**
     * Delete a submission.
     *
     * @param Submission $submission
     *
     * @throws \Throwable
     *
     * @return bool
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
                $this->trigger(self::EVENT_AFTER_DELETE_SUBMISSION, new SubmissionEvent([
                    'submission' => $submission,
                ]));

                return true;
            }
        }

        return false;
    }

    /**
     * Email a submission.
     *
     * @param Submission $submission
     * @param mixed      $overrideRecipients [Optional] Override recipients from form settings.
     *
     * @throws \Exception
     * @throws \Throwable
     *
     * @return bool
     */
    public function emailSubmission(Submission $submission, $overrideRecipients = false)
    {
        $form = $submission->getForm();
        $submission->formName = $form->name;
        $success = false;

        // Make sure we have a formId and notifications are enabled
        if (!$submission->formId || !$form->notificationEnabled) {
            return false;
        }

        // Get our recipients
        $notificationRecipients = explode(',', $this->translatedRenderObjectTemplate($form->notificationRecipients, $submission));
        $recipients = $this->parseRecipients($notificationRecipients, $overrideRecipients);

        $notificationEmail = $this->createNotificationEmail($submission);
        $confirmationEmail = $this->createConfirmationEmail($submission);
        $this->addBccToEmails($submission, [$notificationEmail, $confirmationEmail]);

        $event = new EmailSubmissionEvent([
            'submission' => $submission,
            'email'      => $notificationEmail,
        ]);
        $this->trigger(self::EVENT_BEFORE_EMAIL_SUBMISSION, $event);

        // Is the event giving us the go-ahead?
        if ($event->isValid) {
            $success = $this->sendNotificationEmail($submission, $recipients, $notificationEmail);
        }

        // Send copy?
        if ($form->sendCopy) {
            $event = new EmailSubmissionEvent([
                'submission' => $submission,
                'email'      => $confirmationEmail,
            ]);
            $this->trigger(self::EVENT_BEFORE_EMAIL_CONFIRMATION_SUBMISSION, $event);

            // Is the event giving us the go-ahead?
            if ($event->isValid) {
                $success = $this->sendConfirmationEmail($submission, $confirmationEmail);
            }
        }

        return $success;
    }

    /**
     * Get submission email body.
     *
     * @param Submission $submission
     *
     * @throws \Exception
     *
     * @return string
     */
    public function getSubmissionEmailBody(Submission $submission)
    {
        /** @var Form $form */
        $form = $submission->getForm();

        // Get email body
        $variables = [
            'tabs'       => $form->getFieldLayout()->getTabs(),
            'form'       => $form,
            'submission' => $submission,
        ];

        return SimpleForms::$plugin->simpleForms->renderDisplayTemplate('notification', $form->notificationTemplate, $variables);
    }

    /**
     * Get confirmation email body.
     *
     * @param Submission $submission
     *
     * @throws \Exception
     *
     * @return string
     */
    public function getConfirmationEmailBody(Submission $submission)
    {
        /** @var Form $form */
        $form = $submission->getForm();

        // Get email body
        $variables = [
            'tabs'       => $form->getFieldLayout()->getTabs(),
            'form'       => $form,
            'submission' => $submission,
        ];

        $overrideTemplate = $form->confirmationTemplate;
        if (empty($overrideTemplate)) {
            $overrideTemplate = $form->notificationTemplate;
        }

        return SimpleForms::$plugin->simpleForms->renderDisplayTemplate('confirmation', $overrideTemplate, $variables);
    }

    /**
     * Parse a string through an object and environment variables.
     *
     * @param string $string
     * @param mixed  $object
     *
     * @throws \Throwable
     * @throws \yii\base\Exception
     *
     * @return string
     */
    private function translatedRenderObjectTemplate($string, $object = null)
    {
        return Craft::$app->getView()->renderObjectTemplate(Craft::t('site', $string), $object);
    }

    /**
     * @param array $recipients
     * @param bool  $overrideRecipients
     *
     * @return array|bool
     */
    private function parseRecipients(array $recipients, bool $overrideRecipients)
    {
        if ($overrideRecipients !== false) {
            if (is_array($overrideRecipients) && count($overrideRecipients)) {
                $recipients = $overrideRecipients;
            } elseif (is_string($overrideRecipients)) {
                $recipients = explode(',', $overrideRecipients);
            }
        }

        $recipients = array_unique($recipients);
        array_walk($recipients, 'trim');

        return $recipients;
    }

    /**
     * @param Submission $submission
     *
     * @throws \Throwable
     * @throws \yii\base\Exception
     *
     * @return Message
     */
    private function createNotificationEmail(Submission $submission): Message
    {
        $form = $submission->getForm();

        $notificationEmail = new Message();
        $notificationEmail->setHtmlBody($this->getSubmissionEmailBody($submission));
        $notificationEmail->setFrom([
            $this->translatedRenderObjectTemplate($form->notificationSenderEmail, $submission) => $this->translatedRenderObjectTemplate($form->notificationSenderName, $submission),
        ]);

        if (trim($form->notificationSubject) != '') {
            $notificationEmail->setSubject($this->translatedRenderObjectTemplate($form->notificationSubject, $submission));
        } else {
            $notificationEmail->setSubject($this->translatedRenderObjectTemplate('{formName} form was submitted', $submission));
        }

        $replyTo = null;
        if ($form->notificationReplyToEmail) {
            $replyTo = $this->translatedRenderObjectTemplate($form->notificationReplyToEmail, $submission);
            if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $replyTo = null;
            }
        }

        if ($replyTo) {
            $notificationEmail->setReplyTo($replyTo);
        }

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

        return $notificationEmail;
    }

    /**
     * @param Submission $submission
     *
     * @throws \Throwable
     * @throws \yii\base\Exception
     *
     * @return Message
     */
    private function createConfirmationEmail(Submission $submission): Message
    {
        $form = $submission->getForm();

        $confirmationEmail = new Message();
        $confirmationEmail->setHtmlBody($this->getConfirmationEmailBody($submission));
        $confirmationEmail->setFrom([
            $this->translatedRenderObjectTemplate($form->confirmationSenderEmail, $submission) => $this->translatedRenderObjectTemplate($form->confirmationSenderName, $submission),
        ]);
        if (trim($form->confirmationSubject) != '') {
            $confirmationEmail->setSubject($this->translatedRenderObjectTemplate($form->confirmationSubject, $submission));
        } else {
            $confirmationEmail->setSubject($this->translatedRenderObjectTemplate('Thanks for your submission.', $submission));
        }

        return $confirmationEmail;
    }

    /**
     * @param Submission $submission
     * @param Message[]  $messages
     *
     * @throws \Throwable
     * @throws \yii\base\Exception
     */
    private function addBccToEmails(Submission $submission, array $messages): void
    {
        $bccEmailAddress = SimpleForms::$plugin->getSettings()->bccEmailAddress;
        if ($bccEmailAddress) {
            $bccAddresses = explode(',', $bccEmailAddress);
            $bccAddresses = array_unique($bccAddresses);
            array_walk($bccAddresses, 'trim');

            if (count($bccAddresses)) {
                $properBccAddresses = [];

                foreach ($bccAddresses as $bccAddress) {
                    $bccAddress = $this->translatedRenderObjectTemplate($bccAddress, $submission);

                    if (filter_var($bccAddress, FILTER_VALIDATE_EMAIL)) {
                        $properBccAddresses[] = [
                            'email' => $bccAddress,
                        ];
                    }
                }

                if (count($properBccAddresses)) {
                    foreach ($messages as $message) {
                        $message->setBcc($properBccAddresses);
                    }
                }
            }
        }
    }

    /**
     * @param Submission $submission
     * @param $recipients
     * @param Message $notificationEmail
     *
     * @throws \Throwable
     * @throws \yii\base\Exception
     *
     * @return bool
     */
    private function sendNotificationEmail(Submission $submission, $recipients, Message $notificationEmail): bool
    {
        $success = false;

        foreach ($recipients as $recipient) {
            $notificationEmail->setTo($this->translatedRenderObjectTemplate($recipient, $submission));

            if (filter_var(key($notificationEmail->getTo()), FILTER_VALIDATE_EMAIL)) {
                if (Craft::$app->getMailer()->send($notificationEmail)) {
                    $success = true;
                }
            }
        }

        // Fire an 'onEmailSubmission' event
        $event = new EmailSubmissionEvent([
            'submission' => $submission,
            'email'      => $notificationEmail,
            'success'    => $success,
        ]);
        $this->trigger(self::EVENT_AFTER_EMAIL_SUBMISSION, $event);

        return $success;
    }

    /**
     * @param Submission $submission
     * @param Message    $confirmationEmail
     *
     * @throws \Throwable
     * @throws \yii\base\Exception
     *
     * @return bool
     */
    private function sendConfirmationEmail(Submission $submission, Message $confirmationEmail): bool
    {
        $success = false;
        $form = $submission->getForm();
        $sendCopyTo = $submission->{$form->sendCopyTo};

        if ($sendCopyTo && filter_var($sendCopyTo, FILTER_VALIDATE_EMAIL)) {
            $confirmationEmail->setTo($this->translatedRenderObjectTemplate($sendCopyTo, $submission));

            if (Craft::$app->getMailer()->send($confirmationEmail)) {
                $success = true;
            }
        }

        $this->trigger(self::EVENT_AFTER_EMAIL_CONFIRMATION_SUBMISSION, new EmailSubmissionEvent([
            'submission' => $submission,
            'email'      => $confirmationEmail,
            'success'    => $success,
        ]));

        return $success;
    }
}
