<?php
namespace rias\simpleforms\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use rias\simpleforms\records\ExportRecord;
use rias\simpleforms\SimpleForms;

/**
 * simple-forms - Export task
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
        return 'Submissions export';
    }

    /**
     * ExportJob constructor.
     *
     * @param array $config
     * @throws \Exception
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        // Get export
        /** @var ExportRecord $export */
        $export = SimpleForms::$plugin->exportsService->getExportById($this->exportId);
        if ($export) {
            $this->export = $export;
            $this->totalSteps = ceil($export->total / $this->batchSize);
        }
    }

    /**
     * @param \craft\queue\QueueInterface|\yii\queue\Queue $queue
     * @throws \Exception
     */
    public function execute($queue)
    {
        App::maxPowerCaptain();
        Craft::$app->getConfig()->getGeneral()->cacheElementQueries = false;

        $totalSteps = $this->totalSteps;
        if ($totalSteps > 0) {
            for ($step = 0; $step <= $totalSteps; $step++) {
                $this->setProgress($queue, $step / $totalSteps);

                $limit = $this->batchSize;
                $offset = ($step * $limit);

                // Start export
                SimpleForms::$plugin->exportsService->runExport($this->export, $limit ,$offset);
            }
        }

        // Export finished
        $this->export->setAttribute('finished', true);
        $this->export->save(false);
    }
}
