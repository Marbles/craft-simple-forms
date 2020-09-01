<?php

namespace rias\simpleforms\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use rias\simpleforms\records\ExportRecord;
use rias\simpleforms\SimpleForms;

/**
 * simple-forms - Export task.
 */
class ExportJob extends BaseJob
{
    public $exportId;
    public $batchSize;

    /** @var ExportRecord */
    private $export;

    private $totalSteps = 0;

    protected function defaultDescription()
    {
        return Craft::t('simple-forms', 'Submissions export');
    }

    /**
     * ExportJob constructor.
     *
     * @param array $config
     *
     * @throws \Exception
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->batchSize = SimpleForms::$plugin->getSettings()->exportRowsPerSet;
        $this->export = SimpleForms::$plugin->exports->getExportById($this->exportId);
        $this->totalSteps = ceil($this->export->total / $this->batchSize);
    }

    /**
     * @param \craft\queue\QueueInterface|\yii\queue\Queue $queue
     *
     * @throws \Exception
     */
    public function execute($queue)
    {
        App::maxPowerCaptain();
        Craft::$app->getConfig()->getGeneral()->cacheElementQueries = false;

        SimpleForms::$plugin->exports->runExport($this->export);

        // Export finished
        $this->export->setAttribute('finished', true);
        $this->export->save(false);
    }
}
