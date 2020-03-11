<?php
namespace rias\simpleforms\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use rias\simpleforms\elements\Submission;
use rias\simpleforms\records\ExportRecord;
use rias\simpleforms\SimpleForms;

/**
 *
 * @property null|string $confirmationMessage
 */
class ExportElementAction extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('simple-forms', 'Export');
    }

    /**
     * @inheritDoc IElementAction::isDestructive()
     *
     * @return bool
     */
    public static function isDestructive(): bool
    {
        return false;
    }

    /**
     * @param ElementQueryInterface $submissions
     * @return bool
     * @throws \Exception
     */
    public function performAction(ElementQueryInterface $submissions): bool
    {
        // Gather submissions based on form
        $formSubmissions = [];
        /** @var Submission $submission */
        foreach ($submissions->all() as $submission) {
            if (! isset($formSubmissions[$submission->formId])) {
                $formSubmissions[$submission->formId] = [];
            }
            $formSubmissions[$submission->formId][] = $submission->id;
        }

        // Export submission(s)
        foreach ($formSubmissions as $formId => $submissionIds) {
            $total = count($submissionIds);

            $export = new ExportRecord();
            $export->setAttributes([
                'name' => Craft::t('simple-forms', '{total} submission(s)', ['total' => $total]),
                'formId' => $formId,
                'total' => $total,
                'totalCriteria' => $total,
                'submissions' => $submissionIds,
            ]);
            SimpleForms::$plugin->exportsService->saveExport($export);
        }

        // Success!
        $this->setMessage(Craft::t('simple-forms', 'Submissions exported.'));
        return true;
    }
}
