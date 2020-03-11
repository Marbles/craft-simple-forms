<?php
namespace rias\simpleforms\controllers;

use Craft;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\fields\MissingField;
use craft\fields\PlainText;
use craft\helpers\ArrayHelper;
use craft\web\Controller;
use rias\simpleforms\SimpleForms;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;

/**
 * simple-forms - Fields controller
 */
class FieldsController extends Controller
{
    /**
     * Make sure the current has access.
     *
     * @param $id
     * @param $module
     * @throws HttpException
     */
    public function __construct($id, $module)
    {
        parent::__construct($id, $module);

        $user = Craft::$app->getUser()->getIdentity();
        if (! $user->can('accessAmFormsFields')) {
            throw new HttpException(403, Craft::t('simple-forms', 'This action may only be performed by users with the proper permissions.'));
        }
    }

    /**
     * Show fields.
     */
    public function actionIndex()
    {
        $variables = [
            'fields' => Craft::$app->getFields()->getAllFields('simple-forms'),
        ];

        $this->renderTemplate('simple-forms/fields/index', $variables);
    }

    /**
     * Create or edit a field.
     *
     * @param int|null $fieldId
     * @param Field|null $field
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionEditField(int $fieldId = null, Field $field = null)
    {
        $fieldsService = Craft::$app->getFields();

        // The field
        // ---------------------------------------------------------------------

        $missingFieldPlaceholder = null;

        /** @var Field $field */
        if ($field === null && $fieldId !== null) {
            $field = $fieldsService->getFieldById($fieldId);

            if ($field === null) {
                throw new NotFoundHttpException('Field not found');
            }

            if ($field instanceof MissingField) {
                $missingFieldPlaceholder = $field->getPlaceholderHtml();
                $field = $field->createFallback(PlainText::class);
            }
        }

        if ($field === null) {
            $field = $fieldsService->createField(PlainText::class);
        }

        // Supported translation methods
        // ---------------------------------------------------------------------

        $supportedTranslationMethods = [];
        /** @var string[]|FieldInterface[] $allFieldTypes */
        $allFieldTypes = $fieldsService->getAllFieldTypes();

        foreach ($allFieldTypes as $class) {
            if ($class === get_class($field) || $class::isSelectable()) {
                $supportedTranslationMethods[$class] = $class::supportedTranslationMethods();
            }
        }

        // Allowed field types
        // ---------------------------------------------------------------------

        if (!$field->id) {
            $compatibleFieldTypes = $allFieldTypes;
        } else {
            $compatibleFieldTypes = $fieldsService->getCompatibleFieldTypes($field, true);
        }

        /** @var string[]|FieldInterface[] $compatibleFieldTypes */
        $fieldTypeOptions = [];

        foreach ($allFieldTypes as $class) {
            if ($class === get_class($field) || $class::isSelectable()) {
                $compatible = in_array($class, $compatibleFieldTypes, true);
                $fieldTypeOptions[] = [
                    'value' => $class,
                    'label' => $class::displayName() . ($compatible ? '' : ' ⚠️'),
                ];
            }
        }

        // Sort them by name
        ArrayHelper::multisort($fieldTypeOptions, 'label');

        return $this->renderTemplate('simple-forms/fields/_edit', compact(
            'fieldId',
            'field',
            'allFieldTypes',
            'fieldTypeOptions',
            'missingFieldPlaceholder',
            'supportedTranslationMethods',
            'compatibleFieldTypes'
        ));
    }

    /**
     * Save a field.
     *
     * @throws \yii\web\BadRequestHttpException
     * @throws \Throwable
     */
    public function actionSaveField()
    {
        $this->requirePostRequest();

        $fieldsService = Craft::$app->getFields();
        $request = Craft::$app->getRequest();
        $type = $request->getRequiredBodyParam('type');

        Craft::$app->content->fieldContext = 'simple-forms';
        Craft::$app->content->contentTable = '{{%simple-forms_content}}';

        $field = $fieldsService->createField([
            'type' => $type,
            'id' => $request->getBodyParam('fieldId'),
            'name' => $request->getBodyParam('name'),
            'handle' => $request->getBodyParam('handle'),
            'instructions' => $request->getBodyParam('instructions'),
            'translationMethod' => $request->getBodyParam('translationMethod', Field::TRANSLATION_METHOD_NONE),
            'translationKeyFormat' => $request->getBodyParam('translationKeyFormat'),
            'settings' => $request->getBodyParam('types.' . $type),
        ]);

        if (!$fieldsService->saveField($field)) {
            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save field.'));

            // Send the field back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'field' => $field
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Field saved.'));

        return $this->redirectToPostedUrl($field);
    }

    /**
     * Delete a field.
     *
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDeleteField()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        // Override Craft's default context and content
        Craft::$app->content->fieldContext = 'simple-forms';
        Craft::$app->content->contentTable = '{{%simple-forms_content}}';

        $fieldId = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $success = Craft::$app->getFields()->deleteFieldById($fieldId);

        return $this->asJson(['success' => $success]);
    }
}
