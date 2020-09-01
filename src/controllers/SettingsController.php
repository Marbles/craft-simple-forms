<?php

namespace rias\simpleforms\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use rias\simpleforms\models\Settings;
use rias\simpleforms\SimpleForms;
use yii\web\HttpException;

/**
 * simple-forms - Settings controller.
 */
class SettingsController extends Controller
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
        if (!$user->can('accessAmFormsSettings')) {
            throw new HttpException(403, Craft::t('simple-forms', 'This action may only be performed by users with the proper permissions.'));
        }
    }

    /**
     * Redirect index.
     */
    public function actionIndex()
    {
        $this->redirect('simple-forms/settings/general');
    }

    /**
     * Show settings.
     *
     * @param string        $settingsType
     * @param Settings|null $settings
     *
     * @return \yii\web\Response
     */
    public function actionShowSettings(string $settingsType, Settings $settings = null)
    {
        $overrides = Craft::$app->getConfig()->getConfigFromFile(strtolower(SimpleForms::$plugin->handle));

        $variables = [];
        $variables['overrides'] = array_keys($overrides);
        $variables['type'] = $settingsType;
        $variables['settings'] = $settings ?? SimpleForms::$plugin->getSettings();

        return $this->renderTemplate('simple-forms/settings/'.$settingsType, $variables);
    }

    /**
     * Saves settings.
     *
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\web\BadRequestHttpException
     *
     * @return \yii\web\Response
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();
        $settingsType = (string) Craft::$app->getRequest()->getBodyParam('settingsType');
        $postData = Craft::$app->getRequest()->getBodyParam('settings');
        $settings = SimpleForms::$plugin->getSettings();
        foreach ($postData as $settingKey => $value) {
            $settings->$settingKey = $value;
        }

        if (!$settings->validate() || !Craft::$app->getPlugins()->savePluginSettings(SimpleForms::getInstance(), $settings->toArray())) {
            Craft::$app->getSession()->setError(Craft::t('simple-forms', 'Couldn’t save settings.'));

            return $this->actionShowSettings($settingsType, $settings);
        }

        Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
