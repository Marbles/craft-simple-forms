<?php

namespace rias\simpleforms\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use Exception;
use rias\simpleforms\elements\Form;
use rias\simpleforms\records\ExportRecord;
use rias\simpleforms\SimpleForms;
use yii\web\HttpException;
use yii\web\Response;

/**
 * simple-forms - Exports controller.
 */
class ExportsController extends Controller
{
    /**
     * Make sure the current has access.
     *
     * @param $id
     * @param $module
     *
     * @throws HttpException
     */
    public function __construct($id, $module)
    {
        parent::__construct($id, $module);

        /** @var User $user */
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user->can('accessAmFormsExports')) {
            throw new HttpException(403, Craft::t('simple-forms', 'This action may only be performed by users with the proper permissions.'));
        }
    }

    /**
     * Show exports.
     */
    public function actionIndex(): Response
    {
        $variables = [
            'exports' => SimpleForms::$plugin->exports->getAllExports(),
        ];

        return $this->renderTemplate('simple-forms/exports/index', $variables);
    }

    /**
     * Create or edit an export.
     *
     * @param int|null $exportId
     *
     * @throws Exception
     *
     * @return Response
     */
    public function actionEditExport(int $exportId = null): Response
    {
        $variables = [];
        $variables['exportId'] = $exportId;

        // Get export if available
        if ($exportId !== null) {
            $variables['export'] = SimpleForms::$plugin->exports->getExportById($exportId);
        } else {
            $variables['export'] = new ExportRecord();
        }

        // Get available forms
        $variables['availableForms'] = SimpleForms::$plugin->forms->getAllForms();

        // Get available fields
        $variables['currentForm'] = null;
        $variables['availableFields'] = [];

        $formId = (int) Craft::$app->getRequest()->get('formId') ?? $variables['export']->formId ?? current($variables['availableForms'])->id ?? null;
        /** @var Form $form */
        $form = SimpleForms::$plugin->forms->getFormById($formId);
        $variables['form'] = $form;
        $variables['currentForm'] = $form;
        $variables['availableFields'] = SimpleForms::$plugin->exports->getExportFields($form);

        // Render template!
        return $this->renderTemplate('simple-forms/exports/_edit', $variables);
    }

    /**
     * Save an export.
     *
     * @throws Exception
     */
    public function actionSaveExport()
    {
        $this->requirePostRequest();

        // Get export if available
        $export = new ExportRecord();
        $exportId = Craft::$app->getRequest()->getBodyParam('exportId');
        if ($exportId) {
            $export = SimpleForms::$plugin->exports->getExportById($exportId);
        }

        // Get the chosen form
        $export->setAttribute('formId', Craft::$app->getRequest()->getRequiredBodyParam('formId'));
        $export->setAttribute('type', Craft::$app->getRequest()->getRequiredBodyParam('type'));

        // Get proper POST attributes
        $mapping = Craft::$app->getRequest()->getBodyParam($export->formId);

        $criteria = $mapping['criteria'] ?? null;
        if ($criteria) {
            // Remove criteria from mapping
            unset($mapping['criteria']);

            // Get criteria field IDs
            foreach ($criteria['fields'] as $key => $field) {
                $splittedField = explode('-', $field);
                $criteria['fields'][$key] = $splittedField[(count($splittedField) - 1)];
            }

            // Fix fields that work by the criteriaCounter
            // We might've deleted a criteria row, so we have to make sure the rows are corrected
            foreach ($criteria['fields'] as $key => $field) {
                if (!isset($criteria[$field][$key])) {
                    foreach ($criteria[$field] as $subKey => $subValues) {
                        if ($subKey > $key) {
                            $criteria[$field][$key] = $criteria[$field][$subKey];
                            unset($criteria[$field][$subKey]);
                            break;
                        }
                    }
                }
            }

            // Remove unnecessary criteria
            foreach ($criteria as $fieldId => $values) {
                if (is_numeric($fieldId) && !in_array($fieldId, $criteria['fields'])) {
                    unset($criteria[$fieldId]);
                }
            }
        }

        // Export attributes
        $export->setAttributes([
            'name'           => Craft::$app->getRequest()->getBodyParam('name'),
            'totalCriteria'  => null,
            'map'            => $mapping,
            'criteria'       => $criteria,
            'startRightAway' => !(bool) Craft::$app->getRequest()->getBodyParam('save', false),
        ]);

        // Save export
        $result = SimpleForms::$plugin->exports->saveExport($export);

        if (!$result) {
            $message = $export->startRightAway ? 'No submissions exists (with given criteria).' : 'Couldn’t save export.';
            Craft::$app->getSession()->setError(Craft::t('simple-forms', $message));

            // Send the export back to the template
            return Craft::$app->getUrlManager()->setRouteParams([
                'export' => $export,
            ]);
        }

        if ($export->startRightAway) {
            return $result;
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Export saved.'));

            return $this->redirectToPostedUrl($export);
        }
    }

    /**
     * Delete an export.
     *
     * @throws Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDeleteExport(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $result = SimpleForms::$plugin->exports->deleteExportById(
            Craft::$app->getRequest()->getRequiredBodyParam('id')
        );

        return $this->asJson(['success' => $result]);
    }

    /**
     * Restart an export.
     *
     * @throws Exception
     */
    public function actionRestartExport(): Response
    {
        // Find export ID
        $exportId = Craft::$app->getRequest()->getParam('id');
        if (!$exportId) {
            $this->redirect('simple-forms/exports');
        }

        $export = SimpleForms::$plugin->exports->getExportById($exportId);
        SimpleForms::$plugin->exports->restartExport($export);

        return $this->redirect('simple-forms/exports');
    }

    /**
     * Download an export.
     *
     * @throws Exception
     */
    public function actionDownloadExport(): Response
    {
        // Find export ID
        $exportId = Craft::$app->getRequest()->getParam('id');
        if (!$exportId) {
            $this->redirect('simple-forms/exports');
        }

        $export = SimpleForms::$plugin->exports->getExportById($exportId);

        // Is the export finished and do we have a file?
        if (!$export->finished || !file_exists($export->file)) {
            return $this->redirect('simple-forms/exports');
        }

        // Download file!
        return $this->downloadFile($export);
    }

    /**
     * Export a submission.
     *
     * @throws Exception
     */
    public function actionExportSubmission()
    {
        $this->requirePostRequest();

        // Get the submission
        $submissionId = Craft::$app->getRequest()->getRequiredBodyParam('submissionId');
        $submission = SimpleForms::$plugin->submissions->getSubmissionById($submissionId);

        // Export submission
        $export = new ExportRecord();
        $export->setAttributes([
            'name'           => Craft::t('simple-forms', '{date} {total} submission(s)', ['total' => 1, 'date' => (new \DateTime())->format('Y-m-d H:i')]),
            'formId'         => $submission->formId,
            'total'          => 1,
            'totalCriteria'  => 1,
            'submissions'    => [$submissionId],
            'startRightAway' => true,
        ]);
        $result = SimpleForms::$plugin->exports->saveExport($export);

        if ($result) {
            return $result;
        }

        return $this->redirectToPostedUrl($submission);
    }

    /**
     * Get a criteria row.
     *
     * @throws \yii\web\BadRequestHttpException
     * @throws Exception
     */
    public function actionGetCriteria()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $return = [
            'success' => false,
        ];

        // Get required POST data
        $formId = Craft::$app->getRequest()->getRequiredBodyParam('formId');
        $counter = Craft::$app->getRequest()->getRequiredBodyParam('counter');

        // Get the form
        /** @var Form $form */
        $form = SimpleForms::$plugin->forms->getFormById($formId);

        if ($form) {
            // Get form fields
            $fields = SimpleForms::$plugin->exports->getExportFields($form);

            // Get HTML
            $variables = [
                'form'            => $form,
                'fields'          => $fields,
                'criteriaCounter' => $counter,
            ];
            $html = Craft::$app->getView()->renderTemplate('simple-forms/exports/_fields/template', $variables);

            $return = [
                'success'  => true,
                'row'      => $html,
                'headHtml' => Craft::$app->getView()->getHeadHtml(),
                'bodyHtml' => Craft::$app->getView()->getBodyHtml(),
            ];
        }

        return $this->asJson($return);
    }

    /**
     * Force an export file download.
     *
     * @param ExportRecord $export
     *
     * @return Response
     */
    private function downloadFile(ExportRecord $export): Response
    {
        return (new Response())->sendFile($export->file);
    }
}
