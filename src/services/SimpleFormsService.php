<?php
namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use rias\simpleforms\SimpleForms;

/**
 * simple-forms service
 */
class SimpleFormsService extends Component
{
    private $_assetFolders = [];

    /**
     * Handle an error message.
     *
     * @param string $message
     */
    public function handleError($message)
    {
        $e = new Exception($message);
        if (craft()->amForms_settings->isSettingValueEnabled('quietErrors', SimpleFormsModel::SettingGeneral)) {
            AmFormsPlugin::log('Error::', $e->getMessage(), LogLevel::Warning);
        }
        else {
            throw $e;
        }
    }

    /**
     * Get the server path for an asset.
     *
     * @param AssetFileModel $asset
     *
     * @return string
     */
    public function getPathForAsset($asset)
    {
        // Do we know the source folder path?
        if (! isset($this->_assetFolders[ $asset->folderId ])) {
            $assetFolder = craft()->assets->getFolderById($asset->folderId);
            $assetSource = $assetFolder->getSource();
            $assetSettings = $assetSource->settings;
            if (!array_key_exists('path', $assetSettings)) {
                $assetSettings['path'] = '';
            }
            if ($assetFolder->path) {
                $assetSettings['path'] = $assetSettings['path'] . $assetFolder->path;
            }
            $this->_assetFolders[ $asset->folderId ] = $assetSettings['path'];
        }

        return craft()->config->parseEnvironmentString($this->_assetFolders[ $asset->folderId ]);
    }

    /**
     * Get a display (front-end displayForm) template information.
     *
     * @param string $defaultTemplate Which default template are we looking for?
     * @param string $overrideTemplate Which override template was given?
     *
     * @return array
     * @throws \yii\base\Exception
     */
    public function getDisplayTemplateInfo($defaultTemplate, $overrideTemplate)
    {
        // Plugin's default template path
        $templatePath = SimpleForms::$plugin->basePath . '/templates/_display/templates/';

        $settingsName = $defaultTemplate . 'Template';
        $templateSetting = SimpleForms::$plugin->getSettings()->$settingsName;

        if (empty($overrideTemplate) && $templateSetting) {
            $overrideTemplate = $templateSetting;
        }

        // Is the override template set?
        if ($overrideTemplate) {
            // Is the value a folder, or folder with template?
            $templateFile = Craft::$app->getView()->getTemplatesPath() . '/' . $overrideTemplate;

            if (is_dir($templateFile)) {
                // Only a folder was given, so still the default template template
                $templatePath = $templateFile;
            } else {
                // Try to find the template for each available template extension
                foreach (Craft::$app->getConfig()->getGeneral()->defaultTemplateExtensions as $extension) {
                    if (file_exists($templateFile . '.' . $extension)) {
                        $defaultTemplate = $overrideTemplate;
                        $templatePath = Craft::$app->getPath()->getSiteTemplatesPath();
                        break;
                    }
                }
            }
        }

        return ['path' => $templatePath, 'template' => $defaultTemplate];
    }

    /**
     * Render a display (front-end displayForm) template.
     *
     * @param string $defaultTemplate  Which default template are we looking for?
     * @param string $overrideTemplate Which override template was given?
     * @param array  $variables        Template variables.
     *
     * @return string
     */
    public function renderDisplayTemplate($defaultTemplate, $overrideTemplate, $variables)
    {
        // Get the template path
        $templateInfo = $this->getDisplayTemplateInfo($defaultTemplate, $overrideTemplate);

        // Override Craft template path
        $oldPath = Craft::$app->getView()->getTemplatesPath();
        Craft::$app->getView()->setTemplatesPath($templateInfo['path']);

        // Get template HTML
        $html = Craft::$app->getView()->renderTemplate($templateInfo['template'], $variables);

        // Reset templates path
        Craft::$app->getView()->setTemplatesPath($oldPath);

        // Return rendered template!
        return $html;
    }
}
