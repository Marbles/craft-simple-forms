<?php
namespace rias\simpleforms\controllers;

use Craft;
use craft\web\Controller;
use Exception;
use rias\simpleforms\elements\Form;
use rias\simpleforms\jobs\ExportJob;
use rias\simpleforms\records\ExportRecord;
use rias\simpleforms\SimpleForms;
use yii\web\HttpException;

/**
 * simple-forms - Exports controller
 */
class ExportsController extends Controller
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
        if (! $user->can('accessAmFormsExports')) {
            throw new HttpException(403, Craft::t('simple-forms', 'This action may only be performed by users with the proper permissions.'));
        }
    }

    /**
     * Show exports.
     */
    public function actionIndex()
    {
        $variables = [
            'exports' => SimpleForms::$plugin->exportsService->getAllExports(),
        ];

        $this->renderTemplate('simple-forms/exports/index', $variables);
    }

    /**
     * Create or edit an export.
     *
     * @param int|null $exportId
     * @param ExportRecord|null $export
     * @return \yii\web\Response
     * @throws Exception
     */
    public function actionEditExport(int $exportId = null, ExportRecord $export = null)
    {
        $variables['exportId'] = $exportId;

        // Do we have an export model?
        if (! $export) {
            // Get export if available
            if ($exportId) {
                $variables['export'] = SimpleForms::$plugin->exportsService->getExportById($exportId);

                if (! $variables['export']) {
                    throw new Exception(Craft::t('simple-forms', 'No export exists with the ID “{id}”.', ['id' => $variables['exportId']]));
                }
            } else {
                $variables['export'] = new ExportRecord();
            }
        }

        // Get available forms
        $variables['availableForms'] = SimpleForms::$plugin->formsService->getAllForms();

        // Get available fields
        $variables['currentForm'] = null;
        $variables['availableFields'] = [];
        if (isset($_GET['formId'])) {
            /** @var Form $form */
            $form = SimpleForms::$plugin->formsService->getFormById($_GET['formId']);
            if ($form) {
                $variables['currentForm'] = $form;
                $variables['availableFields'] = SimpleForms::$plugin->exportsService->getExportFields($form);
            }
        } elseif ($variables['export']->formId) {
            /** @var Form $form */
            $form = SimpleForms::$plugin->formsService->getFormById($variables['export']->formId);
            if ($form) {
                $variables['currentForm'] = $form;
                $variables['availableFields'] = SimpleForms::$plugin->exportsService->getExportFields($form);
            }
        } else {
            // Get from first form
            $firstForm = current($variables['availableForms']);
            if ($firstForm) {
                $variables['currentForm'] = $firstForm;
                $variables['availableFields'] = SimpleForms::$plugin->exportsService->getExportFields($firstForm);
            }
        }

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
        $exportId = Craft::$app->getRequest()->getBodyParam('exportId');
        if ($exportId) {
            $export = SimpleForms::$plugin->exportsService->getExportById($exportId);

            if (! $export) {
                throw new Exception(Craft::t('simple-forms', 'No export exists with the ID “{id}”.', ['id' => $exportId]));
            }
        }
        else {
            $export = new ExportRecord();
        }

        // Get the chosen form
        $export->setAttribute('formId', Craft::$app->getRequest()->getBodyParam('formId'));

        // Get proper POST attributes
        $mapping = Craft::$app->getRequest()->getBodyParam($export->formId);
        $criteria = isset($mapping['criteria']) ? $mapping['criteria'] : null;
        if ($criteria) {
            // Remove criteria from mapping
            unset($mapping['criteria']);

            // Get criteria field IDs
            foreach ($criteria['fields'] as $key => $field) {
                $splittedField = explode('-', $field);
                $criteria['fields'][$key] = $splittedField[ (count($splittedField) - 1) ];
            }

            // Fix fields that work by the criteriaCounter
            // We might've deleted a criteria row, so we have to make sure the rows are corrected
            foreach ($criteria['fields'] as $key => $field) {
                if (! isset($criteria[$field][$key])) {
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
                if (is_numeric($fieldId) && ! in_array($fieldId, $criteria['fields'])) {
                    unset($criteria[$fieldId]);
                }
            }
        }

        // Export attributes
        $export->setAttributes([
            'name' => Craft::$app->getRequest()->getBodyParam('name'),
            'totalCriteria' => null,
            'map' => $mapping,
            'criteria' => $criteria,
            'startRightAway' => ! (bool) Craft::$app->getRequest()->getBodyParam('save', false),
        ]);

        // Save export
        $result = SimpleForms::$plugin->exportsService->saveExport($export);
        if ($result) {
            if ($export->startRightAway) {
                return Craft::$app->getResponse()->sendContentAsFile($result, 'export.csv', ['forceDownload' => true, 'mimeType' => 'text/csv']);
            } else {
                Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Export saved.'));

                return $this->redirectToPostedUrl($export);
            }
        } else {
            $message = $export->startRightAway ? 'No submissions exists (with given criteria).' : 'Couldn’t save export.';
            Craft::$app->getSession()->setError(Craft::t('simple-forms', $message));

            // Send the export back to the template
            return Craft::$app->getUrlManager()->setRouteParams([
                'export' => $export
            ]);
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
    public function actionDeleteExport()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $result = SimpleForms::$plugin->exportsService->deleteExportById($id);

        return $this->asJson(['success' => $result]);
    }

    /**
     * Restart an export.
     *
     * @throws Exception
     */
    public function actionRestartExport()
    {
        // Find export ID
        $exportId = Craft::$app->getRequest()->getParam('id');
        if (! $exportId) {
            $this->redirect('simple-forms/exports');
        }

        // Get the export
        /** @var ExportRecord $export */
        $export = SimpleForms::$plugin->exportsService->getExportById($exportId);
        if (! $export) {
            throw new Exception(Craft::t('simple-forms', 'No export exists with the ID “{id}”.', ['id' => $exportId]));
        }

        // Restart export
        SimpleForms::$plugin->exportsService->restartExport($export);

        // Redirect
        return $this->redirect('simple-forms/exports');
    }

    /**
     * Download an export.
     *
     * @throws Exception
     */
    public function actionDownloadExport()
    {
        // Find export ID
        $exportId = Craft::$app->getRequest()->getParam('id');
        if (! $exportId) {
            $this->redirect('simple-forms/exports');
        }

        // Get the export
        /** @var ExportRecord $export */
        $export = SimpleForms::$plugin->exportsService->getExportById($exportId);
        if (! $export) {
            throw new Exception(Craft::t('simple-forms', 'No export exists with the ID “{id}”.', ['id' => $exportId]));
        }

        // Is the export finished and do we have a file?
        if (! $export->finished || ! file_exists($export->file)) {
            $this->redirect('simple-forms/exports');
        }

        // Download file!
        $this->_downloadFile($export);
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
        $submission = SimpleForms::$plugin->submissionsService->getSubmissionById($submissionId);
        if (! $submission) {
            throw new Exception(Craft::t('simple-forms', 'No submission exists with the ID “{id}”.', ['id' => $submissionId]));
        }

        // Delete temporarily files from previous single submission exports
        SimpleForms::$plugin->exportsService->deleteTempExportFiles();

        // Export submission
        $export = new ExportRecord();
        $export->setAttributes([
            'name' => Craft::t('simple-forms', '{total} submission(s)', ['total' => 1]),
            'formId' => $submission->formId,
            'total' => 1,
            'totalCriteria' => 1,
            'submissions' => [$submissionId],
            'startRightAway' => true,
        ]);
        $result = SimpleForms::$plugin->exportsService->saveExport($export);

        if ($result) {
            $this->_downloadFile($export);
        } else {
            $this->redirectToPostedUrl($submission);
        }
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
            'success' => false
        ];

        // Get required POST data
        $formId = Craft::$app->getRequest()->getRequiredBodyParam('formId');
        $counter = Craft::$app->getRequest()->getRequiredBodyParam('counter');

        // Get the form
        /** @var Form $form */
        $form = SimpleForms::$plugin->formsService->getFormById($formId);

        if ($form) {
            // Get form fields
            $fields = SimpleForms::$plugin->exportsService->getExportFields($form);

            // Get HTML
            $variables = [
                'form' => $form,
                'fields' => $fields,
                'criteriaCounter' => $counter
            ];
            $html = Craft::$app->getView()->renderTemplate('simple-forms/exports/_fields/template', $variables);

            $return = [
                'success' => true,
                'row' => $html,
                'headHtml' => Craft::$app->getView()->getHeadHtml(),
                'bodyHtml' => Craft::$app->getView()->getBodyHtml(),
            ];
        }

        return $this->asJson($return);
    }

    /**
     * Get total submissions to export that meet the saved criteria.
     *
     * @throws Exception
     */
    public function actionGetTotalByCriteria()
    {
        // Find export ID
        $exportId = Craft::$app->getRequest()->getParam('id');
        if (! $exportId) {
            $this->redirect('simple-forms/exports');
        }

        // Get the export
        /** @var ExportRecord $export */
        $export = SimpleForms::$plugin->exportsService->getExportById($exportId);
        if (! $export) {
            throw new Exception(Craft::t('simple-forms', 'No export exists with the ID “{id}”.', ['id' => $exportId]));
        }

        // Get total submissions by criteria
        SimpleForms::$plugin->exportsService->saveTotalByCriteria($export);

        // Redirect to exports!
        return $this->redirect('simple-forms/exports');
    }

    /**
     * Force an export file download.
     *
     * @param ExportRecord $export
     */
    private function _downloadFile(ExportRecord $export)
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($export->file));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($export->file));
        readfile($export->file);
        die();
    }
}
