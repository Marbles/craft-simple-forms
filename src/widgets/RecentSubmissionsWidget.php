<?php
namespace rias\simpleforms\widgets;

use Craft;
use craft\base\Widget;
use rias\simpleforms\elements\Form;
use rias\simpleforms\SimpleForms;

/**
 *
 * @property string $name
 * @property mixed $bodyHtml
 * @property mixed $settingsHtml
 * @property string $iconPath
 */
class RecentSubmissionsWidget extends Widget
{
    public $form;
    public $limit;
    public $showDate;

    /**
     * @return string
     */
    public static function displayName(): string
    {
        $name = Craft::t('simple-forms', 'Recent submissions');

        return $name;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getTitle(): string
    {
        $title = self::displayName();

        // Add form name, if a form was chosen
        if ($this->form != 0) {
            /** @var Form $form */
            $form = SimpleForms::$plugin->formsService->getFormById($this->form);

            if ($form) {
                $title .= ': ' . $form->name;
            }
        }

        return $title;
    }

    /**
     * @inheritdoc
     */
    public static function iconPath()
    {
        return SimpleForms::$plugin->getBasePath() . '/resources/icon.svg';
    }

    /**
     * @return false|string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function getBodyHtml()
    {
        // Widget settings
        $settings = $this;

        // Set submissions criteria
        $criteria = SimpleForms::$plugin->submissionsService->getCriteria();
        if ($settings->form != 0) {
            $criteria->formId = $settings->form;
        }
        $criteria->limit = $settings->limit;
        $criteria->orderBy = 'id desc';

        $submissions = $criteria->all();
        foreach ($submissions as $submission) {
            Craft::$app->getContent()->populateElementContent($submission);
        }

        return Craft::$app->getView()->renderTemplate('simple-forms/_widgets/recentsubmissions/body', [
            'submissions' => $submissions,
            'settings'    => $settings,
        ]);
    }

    /**
     * @return null|string
     * @throws \Exception
     */
    public function getSettingsHtml()
    {
        $forms = [
            0 => Craft::t('simple-forms', 'All forms')
        ];
        $availableForms = SimpleForms::$plugin->formsService->getAllForms();
        if ($availableForms) {
            /** @var Form $form */
            foreach ($availableForms as $form) {
                $forms[$form->id] = $form->name;
            }
        }

        return Craft::$app->getView()->renderTemplate('simple-forms/_widgets/recentsubmissions/settings', [
           'settings'       => $this,
           'availableForms' => $forms
        ]);
    }
}
