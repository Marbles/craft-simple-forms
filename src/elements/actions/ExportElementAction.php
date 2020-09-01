<?php

namespace rias\simpleforms\elements\actions;

use Box\Spout\Common\Type;
use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use rias\simpleforms\elements\Submission;
use rias\simpleforms\records\ExportRecord;
use rias\simpleforms\SimpleForms;

/**
 * @property null|string $confirmationMessage
 */
class ExportElementAction extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('simple-forms', 'Export');
    }

    /**
     * {@inheritdoc} IElementAction::isDestructive()
     *
     * @return bool
     */
    public static function isDestructive(): bool
    {
        return false;
    }

    /**
     * @param ElementQueryInterface $submissions
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function performAction(ElementQueryInterface $submissions): bool
    {
        // Gather submissions based on form
        $formSubmissions = [];
        foreach ($submissions->all() as $submission) {
            if ($submission instanceof Submission) {
                if (!isset($formSubmissions[$submission->formId])) {
                    $formSubmissions[$submission->formId] = [];
                }
                $formSubmissions[$submission->formId][] = $submission->id;
            }
        }

        // Export submission(s)
        foreach ($formSubmissions as $formId => $submissionIds) {
            $total = count($submissionIds);

            $export = new ExportRecord();
            $export->setAttributes([
                'name'          => Craft::t('simple-forms', '{total} submission(s)', ['total' => $total]),
                'formId'        => $formId,
                'total'         => $total,
                'totalCriteria' => $total,
                'submissions'   => $submissionIds,
                'type'          => Type::CSV,
            ]);
            SimpleForms::$plugin->exports->saveExport($export);
        }

        // Success!
        $this->setMessage(Craft::t('simple-forms', 'Submissions exported. You can view them in the "exports" tab of {name}', ['name' => SimpleForms::$plugin->getSettings()->pluginName]));

        return true;
    }
}
