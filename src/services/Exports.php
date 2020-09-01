<?php

namespace rias\simpleforms\services;

use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Writer\XLSX\Writer;
use Craft;
use craft\base\Component;
use craft\base\Field;
use craft\fields\Date;
use craft\fields\PlainText;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use Exception;
use rias\simpleforms\elements\Form;
use rias\simpleforms\jobs\ExportJob;
use rias\simpleforms\records\ExportRecord;
use rias\simpleforms\SimpleForms;

/**
 * simple-forms - Exports service.
 */
class Exports extends Component
{
    private $delimiter;
    private $ignoreMatrixMultipleRows;

    public function __construct()
    {
        parent::__construct();

        $this->delimiter = SimpleForms::$plugin->getSettings()->delimiter;
        $this->ignoreMatrixMultipleRows = SimpleForms::$plugin->getSettings()->ignoreMatrixMultipleRows;
    }

    /**
     * Get all exports.
     *
     * @return array|null
     */
    public function getAllExports()
    {
        $exportRecords = ExportRecord::find()->orderBy('id desc')->all();
        if (!empty($exportRecords)) {
            return $exportRecords;
        }
    }

    /**
     * Get an export by its ID.
     *
     * @param int $id
     *
     * @throws Exception
     *
     * @return ExportRecord
     */
    public function getExportById($id): ExportRecord
    {
        $export = ExportRecord::find()->where(Db::parseParam('id', $id))->one();

        if (!$export || !$export instanceof ExportRecord) {
            throw new Exception(Craft::t('simple-forms', 'No export exists with the ID “{id}”.', ['id' => $id]));
        }

        return $export->decodeAttributes();
    }

    public function getExportsByFormId(int $id)
    {
        $exports = ExportRecord::find()->where(Db::parseParam('formId', $id))->all();

        foreach ($exports as $export) {
            if ($export instanceof ExportRecord) {
                $export->decodeAttributes();
            }
        }

        return $exports;
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
                'id'     => 'id',
                'handle' => 'id',
                'name'   => Craft::t('simple-forms', 'id'),
                'type'   => PlainText::class,
            ]),
            'title' => Craft::$app->getFields()->createField([
                'id'     => 'title',
                'handle' => 'title',
                'name'   => Craft::t('simple-forms', 'Title'),
                'type'   => PlainText::class,
            ]),
            'dateCreated' => Craft::$app->getFields()->createField([
                'id'     => 'dateCreated',
                'handle' => 'dateCreated',
                'name'   => Craft::t('simple-forms', 'Date created'),
                'type'   => Date::class,
            ]),
            'dateUpdated' => Craft::$app->getFields()->createField([
                'id'     => 'dateUpdated',
                'handle' => 'dateUpdated',
                'name'   => Craft::t('simple-forms', 'Date updated'),
                'type'   => Date::class,
            ]),
            'submittedFrom' => Craft::$app->getFields()->createField([
                'id'     => 'submittedFrom',
                'handle' => 'submittedFrom',
                'name'   => Craft::t('simple-forms', 'Submitted from'),
                'type'   => PlainText::class,
            ]),
        ];

        // Get fieldlayout fields
        foreach ($form->getFieldLayout()->getTabs() as $tab) {
            // Tab fields
            $fields = $tab->getFields();
            /** @var Field $field */
            foreach ($fields as $field) {
                // Add to fields
                if (in_array(get_class($field), SimpleForms::$supportedFields)) {
                    $exportFields[$field->handle] = $field;
                }
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
     *
     * @return bool|string
     */
    public function saveExport(ExportRecord $export)
    {
        if (!$export->validate()) {
            return false;
        }

        $export->setAttribute('total', $export->calculateTotal());

        if ($export->startRightAway) {
            return $this->runExport($export);
        }

        if ($export->save(false)) {
            Craft::$app->getQueue()->push(new ExportJob(['exportId' => $export->id]));
            Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Export started.'));

            return true;
        }

        return false;
    }

    /**
     * Delete an export.
     *
     * @param int $id
     *
     * @throws Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     *
     * @return bool
     */
    public function deleteExportById($id)
    {
        $export = $this->getExportById($id);

        if (file_exists($export->file)) {
            unlink($export->file);
        }

        return $export->delete() > 0;
    }

    /**
     * Restart an export.
     *
     * @param ExportRecord $export
     *
     * @throws Exception
     * @throws \yii\base\Exception
     */
    public function restartExport(ExportRecord $export)
    {
        // Delete old export
        if (file_exists($export->file)) {
            unlink($export->file);
        }

        // Reset finished
        $export->setAttribute('finished', false);

        // Create a new export file
        $export->setAttribute('file', $this->getExportFilePath($export));

        // Save export and start export!
        if ($this->saveExport($export)) {
            Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Export started.'));
        }
    }

    /**
     * Run an export.
     *
     * @param ExportRecord $export
     *
     * @throws Exception
     *
     * @return bool|string
     */
    public function runExport(ExportRecord $export)
    {
        App::maxPowerCaptain();

        $submissions = $export->getSubmissions();
        $submissionsCount = count($submissions);
        $export->setAttribute('totalCriteria', $submissionsCount);
        if ($submissionsCount === 0) {
            return false;
        }

        Craft::info("Submissions count: $submissionsCount", 'simple-forms');

        /* @var Writer $writer */
        $export->file = $this->getExportFilePath($export);
        Craft::info("Export type: $export->type", 'simple-forms');
        switch ($export->type) {
            case 'xlsx':
                $writer = WriterFactory::create(Type::XLSX);
                $writer->setShouldUseInlineStrings(false);
                break;
            default:
                /** @var \Box\Spout\Writer\CSV\Writer $writer */
                $writer = WriterFactory::create(Type::CSV);
                $writer->setFieldDelimiter(';');
                break;
        }

        Craft::info("Export start right away: $export->startRightAway", 'simple-forms');
        if ($export->startRightAway) {
            $writer->openToBrowser($export->file);
        } else {
            $writer->openToFile($export->file);
        }

        if ($export->map) {
            $included = $export->map['included'];
            $fieldsToExport = collect($export->map['fields'])->filter(function ($field, $handle) use ($included) {
                return (int) $included[$handle] !== 0;
            });
        } else {
            $form = SimpleForms::$plugin->forms->getFormById($export->formId);
            $fieldsToExport = collect($this->getExportFields($form))->map(function ($field) {
                return $field->name;
            });
        }

        // Add header row
        $writer->addRow($fieldsToExport->values()->toArray());

        // Export submissions
        foreach ($submissions as $submission) {
            $writer->addRow($submission->export($fieldsToExport->keys()->toArray()));
        }

        $writer->close();

        return true;
    }

    /**
     * Get export path.
     *
     * @throws \yii\base\Exception
     *
     * @return string
     */
    public function getExportPath()
    {
        return Craft::$app->getPath()->getStoragePath().'/simpleFormsExport/';
    }

    /**
     * @param ExportRecord $export
     *
     * @throws \yii\base\Exception
     * @throws Exception
     *
     * @return string
     */
    public function getExportFilePath(ExportRecord $export)
    {
        if (!$export->name && $export->formId) {
            $form = SimpleForms::$plugin->forms->getFormById($export->formId);
        }

        $path = $this->getExportPath().($export->name ? $export->name : (isset($form) ? $form->name : 'export')).'.'.($export->type ?? 'csv');

        if (!$export->startRightAway && !file_exists($path)) {
            FileHelper::writeToFile($path, '');
        }

        return $path;
    }
}
