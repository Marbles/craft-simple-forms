<?php
/**
 * Forms for Craft.
 *
 * @author    Hubert Prein
 */

namespace rias\simpleforms;

use Craft;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\fields\Assets;
use craft\fields\Checkboxes;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Email;
use craft\fields\MultiSelect;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\fields\Url;
use craft\services\Dashboard;
use craft\services\Fields;
use craft\services\Plugins;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use rias\simpleforms\assetbundles\simpleforms\SimpleFormsAsset;
use rias\simpleforms\fields\Form;
use rias\simpleforms\fields\Submission;
use rias\simpleforms\models\Settings;
use rias\simpleforms\services\AntiSpam as AntiSpamService;
use rias\simpleforms\services\Exports as ExportsService;
use rias\simpleforms\services\Forms as FormsService;
use rias\simpleforms\services\Groups as GroupsService;
use rias\simpleforms\services\Install as InstallService;
use rias\simpleforms\services\Notes as NotesService;
use rias\simpleforms\services\Recaptcha as RecaptchaService;
use rias\simpleforms\services\SimpleForms as SimpleFormsService;
use rias\simpleforms\services\Submissions as SubmissionsService;
use rias\simpleforms\variables\SimpleFormsVariable;
use rias\simpleforms\widgets\RecentSubmissionsWidget;
use yii\base\Event;

/**
 * Class SimpleForms.
 *
 *
 * @property SimpleFormsService $simpleForms
 * @property FormsService $forms
 * @property AntiSpamService $antiSpam
 * @property ExportsService $exports
 * @property InstallService $install
 * @property NotesService $notes
 * @property RecaptchaService $recaptcha
 * @property SubmissionsService $submissions
 * @property GroupsService $groups
 */
class SimpleForms extends Plugin
{
    /**
     * @var SimpleForms
     */
    public static $plugin;

    public static $supportedFields = [
        Assets::class,
        Checkboxes::class,
        Date::class,
        Dropdown::class,
        Email::class,
        MultiSelect::class,
        Number::class,
        RadioButtons::class,
        PlainText::class,
        Url::class,
    ];

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'rias\simpleforms\console\controllers';
        }

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Craft::$app->getView()->registerAssetBundle(SimpleFormsAsset::class);
        }

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function (RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'simple-forms/forms'                   => 'simple-forms/forms/index',
                'simple-forms/forms/new'               => 'simple-forms/forms/edit-form',
                'simple-forms/forms/edit/<formId:\d+>' => 'simple-forms/forms/edit-form',

                'simple-forms/submissions'                               => 'simple-forms/submissions/index',
                'simple-forms/submissions/edit/<submissionId:\d+>'       => 'simple-forms/submissions/edit-submission',
                'simple-forms/submissions/edit/<submissionId:\d+>/notes' => 'simple-forms/notes/display-notes',

                'simple-forms/fields'                    => 'simple-forms/fields/index',
                'simple-forms/fields/new'                => 'simple-forms/fields/edit-field',
                'simple-forms/fields/edit/<fieldId:\d+>' => 'simple-forms/fields/edit-field',

                'simple-forms/exports'                     => 'simple-forms/exports/index',
                'simple-forms/exports/new'                 => 'simple-forms/exports/edit-export',
                'simple-forms/exports/edit/<exportId:\d+>' => 'simple-forms/exports/edit-export',

                'simple-forms/settings'                         => 'simple-forms/settings/index',
                'simple-forms/settings/<settingsType:{handle}>' => 'simple-forms/settings/show-settings',
            ]);
        });

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('simpleForms', SimpleFormsVariable::class);
            }
        );

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                // Register our custom permissions
                $event->permissions[Craft::t('simple-forms', $this->name)] = $this->customAdminCpPermissions();
            }
        );

        // Handler: EVENT_AFTER_INSTALL_PLUGIN
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    $configFilePath = Craft::$app->config->getConfigFilePath('simple-forms');
                    if (!file_exists($configFilePath)) {
                        copy(SimpleForms::$plugin->basePath.'/config.php', $configFilePath);
                    }
                    $fileConfig = Craft::$app->getConfig()->getConfigFromFile('simple-forms');
                    $settings = array_merge($this->getSettings()->toArray(), $fileConfig);
                    $this->install->install($settings);
                }
            }
        );

        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Form::class;
                $event->types[] = Submission::class;
            }
        );

        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = RecentSubmissionsWidget::class;
            }
        );
    }

    public function getCpNavItem()
    {
        $navItem = parent::getCpNavItem();

        $navItem['label'] = $this->getSettings()->pluginName;

        $navItem['subnav'] = [
            'submissions' => [
                'label' => Craft::t('simple-forms', 'Submissions'),
                'url'   => 'simple-forms/submissions',
            ],
            'forms' => [
                'label' => Craft::t('simple-forms', 'Forms'),
                'url'   => 'simple-forms/forms',
            ],
            'fields' => [
                'label' => Craft::t('simple-forms', 'Fields'),
                'url'   => 'simple-forms/fields',
            ],
            'exports' => [
                'label' => Craft::t('simple-forms', 'Exports'),
                'url'   => 'simple-forms/exports',
            ],
            'settings' => [
                'label' => Craft::t('simple-forms', 'Settings'),
                'url'   => 'simple-forms/settings',
            ],
        ];

        return $navItem;
    }

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /** @return Settings */
    public function getSettings()
    {
        return parent::getSettings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     *
     * @return string The rendered settings HTML
     */
    protected function settingsHtml(): string
    {
        // Get and pre-validate the settings
        $settings = $this->getSettings();
        $settings->validate();
        // Get the settings that are being defined by the config file
        $overrides = Craft::$app->getConfig()->getConfigFromFile(strtolower($this->handle));

        return Craft::$app->view->renderTemplate(
            'simple-forms/settings',
            [
                'settings'  => $this->getSettings(),
                'overrides' => array_keys($overrides),
            ]
        );
    }

    /**
     * Plugin has permissions.
     *
     * @return array
     */
    public function customAdminCpPermissions()
    {
        return [
            'accessAmFormsExports' => [
                'label' => Craft::t('simple-forms', 'Access to exports'),
            ],
            'accessAmFormsFields' => [
                'label' => Craft::t('simple-forms', 'Access to fields'),
            ],
            'accessAmFormsForms' => [
                'label' => Craft::t('simple-forms', 'Access to forms'),
            ],
            'accessAmFormsSettings' => [
                'label' => Craft::t('simple-forms', 'Access to settings'),
            ],
        ];
    }

    /**
     * @throws \Throwable
     */
    protected function beforeUninstall(): bool
    {
        // Override Craft's default context and content
        Craft::$app->getContent()->fieldContext = 'simple-forms';
        Craft::$app->getContent()->contentTable = '{{%simple-forms_content}}';

        // Delete our own context fields
        $fields = Craft::$app->getFields()->getAllFields('simple-forms');
        foreach ($fields as $field) {
            Craft::$app->getFields()->deleteField($field);
        }

        return true;
    }
}
