<?php
namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use craft\base\Field;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\MatrixBlock;
use craft\fields\Assets;
use craft\fields\Checkboxes;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Entries;
use craft\fields\Lightswitch;
use craft\fields\Matrix;
use craft\fields\MultiSelect;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\fields\Table;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\models\MatrixBlockType;
use craft\queue\Queue;
use Exception;
use rias\simpleforms\elements\db\SubmissionsQuery;
use rias\simpleforms\elements\Form;
use rias\simpleforms\elements\Submission;
use rias\simpleforms\jobs\ExportJob;
use rias\simpleforms\records\ExportRecord;
use rias\simpleforms\SimpleForms;
use ZipArchive;

/**
 * simple-forms - Exports service
 */
class ExportsService extends Component
{
    private $_delimiter;
    private $_ignoreMatrixMultipleRows;
    private $_exportFiles = array();
    private $_exportFields = array();
    private $_exportColumns = array();
    private $_exportSpaceCounter = array();

    public function __construct()
    {
        parent::__construct();

        $this->_delimiter = SimpleForms::$plugin->getSettings()->delimiter;
        $this->_ignoreMatrixMultipleRows = SimpleForms::$plugin->getSettings()->ignoreMatrixMultipleRows;
    }

    /**
     * Get all exports.
     *
     * @return array|null
     */
    public function getAllExports()
    {
        $exportRecords = ExportRecord::find()->orderBy('id desc')->all();
        if ($exportRecords) {
            return $exportRecords;
        }
        return null;
    }

    /**
     * Get an export by its ID.
     *
     * @param int $id
     *
     * @return array|\yii\db\ActiveRecord
     */
    public function getExportById($id)
    {
        $export = ExportRecord::find()->where(Db::parseParam('id', $id))->one();
        $export->setAttribute('map', json_decode($export->map, true));
        $export->setAttribute('criteria', json_decode($export->criteria, true));
        return $export;
    }

    /**
     * Get export fields for a form.
     *
     * @param Form $form
     *
     * @return array
     */
    public function getExportFields(Form $form)
    {
        // Standard fields
        $exportFields = [
            'id' => Craft::$app->getFields()->createField([
                'id' => 'id',
                'handle' => 'id',
                'name' => Craft::t('simple-forms', 'id'),
                'type' => PlainText::class,
            ]),
            'title' => Craft::$app->getFields()->createField([
                'id' => 'title',
                'handle' => 'title',
                'name' => Craft::t('simple-forms', 'Title'),
                'type' => PlainText::class,
            ]),
            'dateCreated' => Craft::$app->getFields()->createField([
                'id' => 'dateCreated',
                'handle' => 'dateCreated',
                'name' => Craft::t('simple-forms', 'Date created'),
                'type' => Date::class,
            ]),
            'dateUpdated' => Craft::$app->getFields()->createField([
                'id' => 'dateUpdated',
                'handle' => 'dateUpdated',
                'name' => Craft::t('simple-forms', 'Date updated'),
                'type' => Date::class,
            ]),
            'submittedFrom' => Craft::$app->getFields()->createField([
                'id' => 'submittedFrom',
                'handle' => 'submittedFrom',
                'name' => Craft::t('simple-forms', 'Submitted from'),
                'type' => PlainText::class,
            ])
        ];

        // Get fieldlayout fields
        foreach ($form->getFieldLayout()->getTabs() as $tab) {
            // Tab fields
            $fields = $tab->getFields();
            /** @var Field $field */
            foreach ($fields as $field) {
                // Add to fields
                $exportFields[$field->handle] = $field;
            }
        }

        return $exportFields;
    }

    /**
     * Save an export.
     *
     * @param ExportRecord $export
     *
     * @throws Exception
     * @return bool
     */
    public function saveExport(ExportRecord $export)
    {
        $isNewExport = ! $export->id;

        // Get the form
        $form = SimpleForms::$plugin->formsService->getFormById($export->formId);
        if (! $form) {
            throw new Exception(Craft::t('simple-forms', 'No form exists with the ID “{id}”.', ['id' => $export->formId]));
        }

        // Export attributes
        if ($isNewExport) {
            // Do we need to get the total submissions to export?
            if (! $export->submissions && ! $export->startRightAway) {
                // Set total records to export
                $export->total = (new Query())
                                        ->select('COUNT(*)')
                                        ->from('{{%simple-forms_submissions}}')
                                        ->where('formId=:formId', [':formId' => $export->formId])
                                        ->scalar();
            }

            // We need to create an export file when we already have the submissions
            // Or when we have no manually given submissions and don't export right way
            if (! $export->startRightAway || $export->submissions) {
                // Create a new export file
                $export->file = $this->_createExportFile($export, $form);
            }
        }

        // Validate the attributes
        $export->validate();

        if (! $export->hasErrors()) {
            if ($export->startRightAway) {
                // Get max power
                App::maxPowerCaptain();

                // Run the export!
                return $this->runExport($export);
            } else {
                // Save the export!
                $result = $export->save(false); // Skip validation now

                // Start export task?
                if ($result && $isNewExport) {
                    // Start task
                    $params = [
                        'exportId'  => $export->id,
                        'batchSize' => SimpleForms::$plugin->getSettings()->exportRowsPerSet,
                    ];

                    $job = new ExportJob($params);
                    $job->execute(new Queue());
                    //Craft::$app->getQueue()->push(new ExportJob($params));

                    // Notify user
                    Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Export started.'));
                }

                return $result;
            }
        }

        return false;
    }

    /**
     * Delete an export.
     *
     * @param int $id
     *
     * @return bool
     * @throws Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteExportById($id)
    {
        /** @var ExportRecord $export */
        $export = $this->getExportById($id);
        if ($export) {
            if (file_exists($export->file)) {
                unlink($export->file);
            }
            return $export->delete();
        }

        return false;
    }

    /**
     * Delete export files for a form.
     *
     * @param AmForms_FormModel $form
     *
     * @return bool
     */
    public function deleteExportFilesForForm(AmForms_FormModel $form)
    {
        $files = IOHelper::getFiles($this->_getExportPath());
        if (! $files || ! count($files)) {
            return false;
        }

        foreach ($files as $file) {
            if (strpos($file, $form->handle) !== false) {
                IOHelper::deleteFile($file);
            }
        }
    }

    /**
     * Restart an export.
     *
     * @param ExportRecord $export
     * @throws Exception
     * @throws \yii\base\Exception
     */
    public function restartExport(ExportRecord $export)
    {
        // Get the form
        /** @var Form $form */
        $form = SimpleForms::$plugin->formsService->getFormById($export->formId);
        if (! $form) {
            throw new Exception(Craft::t('simple-forms', 'No form exists with the ID “{id}”.', ['id' => $export->formId]));
        }

        // Delete old export
        if (file_exists($export->file)) {
            unlink($export->file);
        }

        // Reset finished
        $export->setAttribute('finished',  false);
        if (! $export->submissions) {
            // Set total records to export
            $export->setAttribute(
                        'total',
                        (new Query())
                            ->select('COUNT(*)')
                            ->from('{{%simple-forms_submissions}}')
                            ->where('formId=:formId', array(':formId' => $export->formId))
                            ->scalar());
        }
        // Create a new export file
        $export->setAttribute('file', $this->_createExportFile($export, $form));
        $export->setAttribute('submissions', is_string($export->submissions) ? json_decode($export->submissions) : $export->submissions);

        // Save export and start export!
        if ($this->saveExport($export)) {
            // Start task
            $params = [
                'exportId'  => $export->id,
                'batchSize' => SimpleForms::$plugin->getSettings()->exportRowsPerSet,
            ];
            Craft::$app->getQueue()->push(new ExportJob($params));

            // Notify user
            Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Export started.'));
        }
    }

    /**
     * Save total submissions that meet the saved criteria.
     *
     * @param ExportRecord $export
     * @throws Exception
     */
    public function saveTotalByCriteria(ExportRecord $export)
    {
        // Set submissions criteria
        $params = [
            'limit' => null,
            'formId' => $export->formId
        ];
        $criteria = SimpleForms::$plugin->submissionsService->getCriteria($params);

        // Add export criteria
        $this->_addExportCriteria($export, $criteria);

        // Get total!
        $export->setAttribute('totalCriteria', count($criteria->all()));

        // Save export!
        $this->saveExport($export);
    }

    /**
     * Run an export.
     *
     * @param ExportRecord $export
     * @param int $limit
     * @param int $offset
     *
     * @return bool
     * @throws Exception
     */
    public function runExport(ExportRecord $export, $limit = null, $offset = null)
    {
        // Validate export file (if send by task)
        if (! file_exists($export->file) && ! $export->startRightAway) {
            return false;
        }

        // Get submissions
        $params = [
            'formId' => $export->formId,
            'limit'  => $limit,
            'offset' => $offset
        ];
        // Are there manually given submissions?
        if ($export->submissions) {
            $params['id'] = is_string($export->submissions) ? json_decode($export->submissions) : $export->submissions;
        }
        $criteria = SimpleForms::$plugin->submissionsService->getCriteria($params);
        $this->_addExportCriteria($export, $criteria);
        $submissions = $criteria->all();

        // Add submissions to export file
        if ($submissions && count($submissions) > 0) {
            // Get form
            /** @var Form $form */
            $form = SimpleForms::$plugin->formsService->getFormById($export->formId);
            if (! $form) {
                return false;
            }

            // Get field types
            $fields = $this->getExportFields($form);

            // Export submission to a zip file?
            if ($export->submissions) {
                // Add all fields
                $this->_exportFields[$export->id] = $fields;

                // Export submission
                /** @var Submission $submission */
                foreach ($submissions as $submission) {
                    Craft::$app->getContent()->populateElementContent($submission);
                    $this->_exportSubmissionToZip($export, $submission);
                }
            } else {
                // Get the export file
                if ($export->startRightAway) {
                    // Open output buffer
                    ob_start();

                    // Write to output stream
                    $this->_exportFiles['manual'] = fopen('php://output', 'w');

                    // Create columns
                    fputcsv($this->_exportFiles['manual'], $this->_getExportColumns($export, $form), $this->_delimiter);
                } else {
                    $this->_exportFiles[$export->id] = fopen($export->file, 'a');
                }

                // Get field handles and columns that should be included
                $columnCounter = 0;
                $this->_exportFields[$export->id] = array();
                foreach ($export->map['fields'] as $fieldHandle => $columnName) {
                    if ($export->map['included'][$fieldHandle] && isset($fields[$fieldHandle])) {
                        // Add field to export fields
                        $field = $fields[$fieldHandle];
                        if (is_array($field)) {
                            $field = (object) $field; // Fix standard fields
                        }
                        $this->_exportFields[$export->id][$fieldHandle] = $field;

                        // Remember how much space this field is taking
                        $spaceCounter = 0;

                        // Add column so we know where to place the data later
                        switch ($field) {
                            case Matrix::class:
                                $blockTypes = $field->getFieldType()->getSettings()->getBlockTypes();
                                /** @var MatrixBlockType $blockType */
                                foreach ($blockTypes as $blockType) {
                                    $blockTypeFields = $blockType->getFields();

                                    $this->_exportColumns[$export->id][$field->handle . ':' . $blockType->handle] = $columnCounter;

                                    $columnCounter += count($blockTypeFields);

                                    $spaceCounter += count($blockTypeFields);
                                }
                                break;
                            default:
                                $this->_exportColumns[$export->id][$field->handle] = $columnCounter;

                                $spaceCounter ++;
                                break;
                        }

                        $columnCounter ++;

                        $this->_exportSpaceCounter[$export->id][$field->handle] = $spaceCounter;
                    }
                }

                // Export submission model
                foreach ($submissions as $submission) {
                    $this->_exportSubmission($export, $submission);
                }

                // Close export file
                fclose($this->_exportFiles[ ($export->startRightAway ? 'manual' : $export->id) ]);

                if ($export->startRightAway) {
                    // Close buffer and return data
                    $data = ob_get_clean();

                    // Use windows friendly newlines
                    $data = str_replace("\n", "\r\n", $data);

                    return $data;
                }
            }
        }

        return true;
    }

    /**
     * Get temporarily created export files.
     *
     * Note: these files were created by single submission export.
     *
     * @param array $exports Array with AmForms_ExportModel to be able to skip files.
     *
     * @return bool|array
     */
    public function getTempExportFiles($exports = array())
    {
        // Get exports folder path
        $folder = $this->_getExportPath();
        if (! is_dir($folder)) {
            return false;
        }

        // Gather files
        $tempFiles = array();
        $skipFiles = array();

        // Do we have any exports available?
        if (is_array($exports) && count($exports)) {
            foreach ($exports as $export) {
                $skipFiles[] = $export->file;
            }
        }

        // Find temp files
        $handle = opendir($folder);
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..' || substr($file, 0, 1) == '.') {
                continue;
            }
            if (! in_array($folder . $file, $skipFiles)) {
                $tempFiles[] = $folder . $file;
            }
        }
        closedir($handle);

        // Return files if found any!
        return count($tempFiles) ? $tempFiles : false;
    }

    /**
     * Delete temporarily created export files.
     *
     * @return bool
     */
    public function deleteTempExportFiles()
    {
        // Get temp files
        $exports = $this->getAllExports();
        $files = $this->getTempExportFiles($exports);

        // Delete files
        if ($files) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            return true;
        }

        // We don't have any files to delete
        return false;
    }

    /**
     * Get export path.
     *
     * @return string
     * @throws \yii\base\Exception
     */
    private function _getExportPath()
    {
        return Craft::$app->getPath()->getStoragePath() . '/simpleFormsExport/';
    }

    /**
     * Create an export file.
     *
     * @param ExportRecord $export
     * @param Form $form
     *
     * @return string
     * @throws \yii\base\Exception
     */
    private function _createExportFile(ExportRecord $export, Form $form)
    {
        // Determine folder
        $folder = $this->_getExportPath();
        if (!file_exists($folder)) {
            FileHelper::createDirectory($folder);
        }

        // What type of export?
        $fileExtension = ($export->submissions) ? '.zip' : '.csv';

        // Create export file
        $file = $folder . $form->handle . $fileExtension;
        $counter = 1;
        while (file_exists($file) || ($handle = fopen($file, 'w')) === false) {
            $file = $folder . $form->handle . $counter . $fileExtension;
            $counter ++;
        }
        fclose($handle);

        // Only add columns when we are not working with a zip file
        if (! $export->submissions) {
            // Add columns to export file
            $exportFile = fopen($file, 'w');
            fputcsv($exportFile, $this->_getExportColumns($export, $form), $this->_delimiter);
            fclose($exportFile);
        }

        // Return file path
        return $file;
    }

    /**
     * Get export columns.
     *
     * @param ExportRecord $export
     * @param Form $form
     *
     * @return array
     */
    private function _getExportColumns(ExportRecord $export, Form $form)
    {
        $columns = array();

        // Ignore Matrix fields in column name setting
        $ignoreMatrixName = SimpleForms::$plugin->getSettings()->ignoreMatrixFieldAndBlockNames;

        // Get fields
        $fields = $this->getExportFields($form);
        Craft::debug($fields, 'simple-forms');
        Craft::debug($export->map, 'simple-forms');

        // Get column names
        foreach ($export->map['fields'] as $fieldHandle => $columnName) {
            // Should the field be included?
            if ($export->map['included'][$fieldHandle] && isset($fields[$fieldHandle])) {
                // Actual field
                $field = $fields[$fieldHandle];
                if (is_array($field)) {
                    $field = (object) $field; // Fix standard fields
                }

                // Add column based on the field type
                switch (get_class($field)) {
                    case Matrix::class:
                        $blockTypes = $field->getFieldType()->getSettings()->getBlockTypes();
                        foreach ($blockTypes as $blockType) {
                            $blockTypeFields = $blockType->getFields();

                            foreach ($blockTypeFields as $blockTypeField) {
                                $columns[] = (! $ignoreMatrixName ? $columnName . ':' . $blockType->name . ':' : '') . $blockTypeField->name;
                            }
                        }
                        break;

                    default:
                        $columns[] = $columnName;
                        break;
                }
            }
        }
        Craft::debug($columns, 'simple-forms');
        return $columns;
    }

    /**
     * Add export criteria.
     *
     * @param ExportRecord $export
     * @param SubmissionsQuery &$criteria
     *
     * @return bool
     * @throws Exception
     */
    private function _addExportCriteria(ExportRecord $export, &$criteria)
    {
        // Do we even have criteria?
        if (! $export->criteria) {
            return false;
        }

        // Get form
        /** @var Form $form */
        $form = SimpleForms::$plugin->formsService->getFormById($export->formId);
        if (! $form) {
            return false;
        }

        // Gather related criteria
        $relatedTo = ['or'];

        // Get fields
        $fields = $this->getExportFields($form);
        foreach ($fields as $field) {
            // Is field set in criteria?
            if (! isset($export->criteria[ $field->id ])) {
                continue;
            }

            // Add criteria based on field type
            switch (get_class($field)) {
                case Assets::class:
                case Entries::class:
                    foreach ($export->criteria[ $field->id ] as $criteriaValue) {
                        if (! empty($criteriaValue) && is_array($criteriaValue) && count($criteriaValue)) {
                            $relatedTo[] = $criteriaValue[0];
                        }
                    }
                    break;

                case Checkboxes::class:
                    $setCriteria = array();
                    foreach ($export->criteria[ $field->id ] as $criteriaValue) {
                        if (! empty($criteriaValue)) {
                            foreach ($criteriaValue as $subCriteriaValue) {
                                $setCriteria[] = '*"' . $subCriteriaValue . '"*';
                            }
                        }
                    }
                    $criteria->{$field->handle} = count($setCriteria) ? array_merge(array('or'), $setCriteria) : '[]';
                    break;

                case Lightswitch::class:
                    $valueFound = false;
                    foreach ($export->criteria[ $field->id ] as $criteriaValue) {
                        if (! empty($criteriaValue)) {
                            $valueFound = true;
                            $criteria->{$field->handle} = $criteriaValue;
                        }
                    }
                    if (! $valueFound) {
                        $criteria->{$field->handle} = 'not 1';
                    }
                    break;

                case Dropdown::class:
                case PlainText::class:
                case RadioButtons::class:
                    $setCriteria = array('or');
                    foreach ($export->criteria[ $field->id ] as $criteriaValue) {
                        if (! empty($criteriaValue)) {
                            $setCriteria[] = $criteriaValue;
                        }
                    }
                    $criteria->{$field->handle} = $setCriteria;
                    break;
            }
        }

        // Set relations criteria
        if (count($relatedTo) > 1) {
            $criteria->relatedTo = $relatedTo;
        }
    }

    /**
     * Export submission.
     *
     * @param ExportRecord $export
     * @param mixed $submission
     * @param bool $returnData
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    private function _exportSubmission(ExportRecord $export, $submission, $returnData = false)
    {
        // Populate element content
        Craft::$app->getContent()->populateElementContent($submission);

        // Row data
        $data = array();
        $columnCounter = 0;

        // Multiple rows data
        $hasMoreRows = false;
        $moreRowsData = array();

        if ($returnData) {
            $fields = array();
            $fieldLayout = $submission->getFieldLayout();
            foreach ($fieldLayout->getFields() as $fieldLayoutField) {
                $field = $fieldLayoutField->getField();
                $fields[$field->handle] = $field;
            }
        } else {
            $fields = $this->_exportFields[$export->id];
        }

        foreach ($fields as $fieldHandle => $field) {
            switch (get_class($field)) {
                case Assets::class:
                    $fieldExportData = array();
                    /** @var Asset $fieldData */
                    foreach ($submission->$fieldHandle->all() as $fieldData) {
                        $fieldExportData[] = $fieldData->getUrl();
                    }
                    $data[] = implode(', ', $fieldExportData);
                    break;
                case Entries::class:
                    $fieldExportData = array();
                    /** @var Entry $fieldData */
                    foreach ($submission->$fieldHandle->all() as $fieldData) {
                        $fieldExportData[] = $fieldData->title;
                    }
                    $data[] = implode(', ', $fieldExportData);
                    break;

                case Checkboxes::class:
                case MultiSelect::class:
                    if (isset($submission->$fieldHandle) && count($submission->$fieldHandle)) {
                        $fieldExportData = array();
                        foreach ($submission->$fieldHandle as $fieldData) {
                            $fieldExportData[] = $fieldData->value;
                        }
                        $data[] = implode(', ', $fieldExportData);
                    }
                    else {
                        $data[] = '';
                    }
                    break;

                case Lightswitch::class:
                    $data[] = $submission->$fieldHandle ? Craft::t('simple-forms', 'Yes') : Craft::t('simple-forms', 'No');
                    break;

                case Matrix::class:
                    $blockCounter = 0;
                    $matrixBlocks = $submission->$fieldHandle->all();
                    if (! $matrixBlocks) {
                        // No matrix data, so we have to add empty cells!
                        for ($i = 1; $i <= $this->_exportSpaceCounter[$export->id][$fieldHandle]; $i++) {
                            $data[] = '';
                        }
                    } else {
                        /** @var MatrixBlock $matrixBlock */
                        foreach ($matrixBlocks as $matrixBlock) {
                            $matrixBlockType = $matrixBlock->getType();
                            $blockData = $this->_exportSubmission($export, $matrixBlock, true);

                            // Column counter
                            $startFrom = $this->_exportColumns[$export->id][$fieldHandle . ':' . $matrixBlockType->handle];

                            // Multiple blocks?
                            if (count($matrixBlocks) > 1 && $blockCounter > 0 && ! $this->_ignoreMatrixMultipleRows) {
                                $hasMoreRows = true;
                                $moreRowsData[$startFrom][] = $blockData;
                            }
                            else {
                                if (! $this->_ignoreMatrixMultipleRows) {
                                    // Empty cells till we've reached the block type
                                    for ($i = 0; $i < ($startFrom - $columnCounter); $i++) {
                                        $data[] = '';
                                    }
                                }

                                // We just have one block or we are adding the first block
                                $spaceCounter = 0;
                                foreach ($blockData as $blockValue) {
                                    $data[] = $blockValue;
                                    $spaceCounter ++;
                                }

                                if (! $this->_ignoreMatrixMultipleRows) {
                                    // Empty cells till we've reached the next field, if necessary
                                    if ($startFrom == $columnCounter) {
                                        for ($i = 0; $i < ($this->_exportSpaceCounter[$export->id][$fieldHandle] - $spaceCounter); $i++) {
                                            $data[] = '';
                                        }
                                    }
                                }
                            }

                            $blockCounter ++;
                        }
                    }
                    break;

                case Table::class:
                    if (isset($submission->$fieldHandle) && count($submission->$fieldHandle)) {
                        $fieldExportData = array();
                        foreach ($submission->$fieldHandle as $fieldData) {
                            foreach ($fieldData as $columnKey => $columnValue) {
                                if (substr($columnKey, 0, 3) == 'col' && $columnValue) {
                                    $fieldExportData[] = $columnValue;
                                }
                            }
                        }
                        $data[] = implode(', ', $fieldExportData);
                    }
                    else {
                        $data[] = '';
                    }
                    break;

                default:
                    if (is_object($submission->$fieldHandle) && get_class($submission->$fieldHandle) == \DateTime::class) {
                        $data[] = str_replace(array("\n", "\r", "\r\n", "\n\r"), ' ', $submission->$fieldHandle->format('Y-m-d H:i:s'));
                    } else {
                        $data[] = str_replace(array("\n", "\r", "\r\n", "\n\r"), ' ', $submission->$fieldHandle);
                    }
                    break;
            }

            $columnCounter ++;
        }

        // Either return the data or add to CSV
        if ($returnData) {
            return $data;
        }
        fputcsv($this->_exportFiles[ ($export->startRightAway ? 'manual' : $export->id) ], $data, $this->_delimiter);

        // Add more rows?
        if ($hasMoreRows) {
            foreach ($moreRowsData as $columnCounter => $rows) {
                foreach ($rows as $row) {
                    // This row's data
                    $data = array();

                    // Empty cells till we've reached the data
                    for ($i = 0; $i < $columnCounter; $i++) {
                        $data[] = '';
                    }
                    // Add row data
                    foreach ($row as $rowData) {
                        $data[] = $rowData;
                    }

                    // Add row to CSV
                    fputcsv($this->_exportFiles[ ($export->startRightAway ? 'manual' : $export->id) ], $data, $this->_delimiter);
                }
            }
        }
    }

    /**
     * Export a submission to the export's zip file.
     *
     * @param ExportRecord $export
     * @param Submission $submission
     * @throws \yii\base\Exception
     * @throws Exception
     */
    private function _exportSubmissionToZip(ExportRecord $export, Submission $submission)
    {
        // Get submission content
        $content = SimpleForms::$plugin->submissionsService->getSubmissionEmailBody($submission);

        // Create submission file
        $fileName = FileHelper::sanitizeFilename($submission->title);
        $fileExtension = '.html';
        $folder = $this->_getExportPath();
        $file = $folder . $fileName . $fileExtension;
        $counter = 1;
        while (($handle = fopen($file, 'w')) === false) {
            $file = $folder . $fileName . $counter . $fileExtension;
            $counter ++;
        }
        fclose($handle);

        // Add content to file
        file_put_contents($file, $content);

        // Add file to zip
        $zip = new ZipArchive();
        $zip->open($export->file);
        $zip->addFile($file, $fileName . $fileExtension);

        // Find possible assets
        foreach ($this->_exportFields[$export->id] as $fieldHandle => $field) {
            if (is_array($field)) {
                $field = (object) $field; // Fix standard fields
            }
            if (get_class($field) == Assets::class) {
                foreach ($submission->$fieldHandle->all() as $asset) {
                    $assetPath = SimpleForms::$plugin->simpleFormsService->getPathForAsset($asset);

                    if (file_exists($assetPath . $asset->filename)) {
                        $zip->addFile($assetPath . $asset->filename);
                    }
                }
            }
        }
        $zip->close();

        // Remove submission file now that's in the zip
        unlink($file);
    }
}
