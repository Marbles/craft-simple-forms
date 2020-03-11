<?php
namespace rias\simpleforms\controllers;

use Craft;
use craft\web\Controller;
use Exception;
use rias\simpleforms\records\NoteRecord;
use rias\simpleforms\SimpleForms;
use yii\web\HttpException;

/**
 * simple-forms - Notes controller
 */
class NotesController extends Controller
{
    /**
     * Display notes
     *
     * @param array $variables
     * @throws HttpException
     * @throws Exception
     */
    public function actionDisplayNotes(int $submissionId = null)
    {
        $variables = ['submissionId' => $submissionId];

        // Do we have a note model?
        if (! isset($variables['note'])) {
            $variables['note'] = new NoteRecord();
        }

        // We require a submission ID
        if (! $variables['submissionId']) {
            throw new HttpException(404);
        }

        // Get submission if available
        $submission = SimpleForms::$plugin->submissionsService->getSubmissionById($variables['submissionId']);
        if (! $submission) {
            throw new Exception(Craft::t('simple-forms', 'No submission exists with the ID “{id}”.', ['id' => $variables['submissionId']]));
        }

        // Get form if available
        $form = SimpleForms::$plugin->formsService->getFormById($submission->formId);
        if (! $form) {
            throw new Exception(Craft::t('simple-forms', 'No form exists with the ID “{id}”.', ['id' => $submission->formId]));
        }

        // Set variables
        $variables['submission'] = $submission;
        $variables['form'] = $form;
        $variables['notes'] = SimpleForms::$plugin->notesService->getNotesBySubmissionId($variables['submissionId']);

        $this->renderTemplate('simple-forms/submissions/_notes', $variables);
    }

    /**
     * Save a note.
     *
     * @throws Exception
     */
    public function actionSaveNote()
    {
        $this->requirePostRequest();

        // Get note if available
        $noteId = Craft::$app->getRequest()->getBodyParam('noteId');
        if ($noteId) {
            $note = SimpleForms::$plugin->notesService->getNoteById($noteId);

            if (! $note) {
                throw new Exception(Craft::t('simple-forms', 'No note exists with the ID “{id}”.', ['id' => $noteId]));
            }
        }
        else {
            $note = new NoteRecord();
        }

        // Note attributes
        $note->setAttribute('submissionId', Craft::$app->getRequest()->getBodyParam('submissionId'));
        $note->setAttribute('name', Craft::$app->getRequest()->getBodyParam('name'));
        $note->setAttribute('text', Craft::$app->getRequest()->getBodyParam('text'));

        // Save note
        if (SimpleForms::$plugin->notesService->saveNote($note)) {
            Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Note saved.'));

            return $this->redirectToPostedUrl($note);
        } else {
            Craft::$app->getSession()->setError(Craft::t('simple-forms', 'Couldn’t save note.'));

            // Send the note back to the template
            return Craft::$app->getUrlManager()->setRouteParams([
                'note' => $note
            ]);
        }
    }

    /**
     * Delete a note.
     *
     * @return \yii\web\Response
     * @throws Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDeleteNote()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        $result = SimpleForms::$plugin->notesService->deleteNoteById($id);
        return $this->asJson(['success' => $result]);
    }
}
