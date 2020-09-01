<?php

namespace rias\simpleforms\controllers;

use Craft;
use craft\web\Controller as BaseController;
use rias\simpleforms\records\FormGroupRecord;
use rias\simpleforms\SimpleForms;

class GroupsController extends BaseController
{
    /**
     * Save a group.
     *
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
     *
     * @return \yii\web\Response
     */
    public function actionSaveGroup()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $group = new FormGroupRecord();
        $group->id = $request->getBodyParam('id');
        $group->name = $request->getRequiredBodyParam('name');

        $isNewGroup = (null === $group->id);

        if (SimpleForms::$plugin->groups->saveGroup($group)) {
            if ($isNewGroup) {
                Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Group added.'));
            }

            return $this->asJson([
                'success' => true,
                'group'   => $group->getAttributes(),
            ]);
        } else {
            return $this->asJson([
                'errors' => $group->getErrors(),
            ]);
        }
    }

    /**
     * Deletes a group.
     *
     * @throws \yii\db\Exception
     * @throws \yii\web\BadRequestHttpException
     * @throws \craft\errors\MissingComponentException
     *
     * @return \yii\web\Response
     */
    public function actionDeleteGroup()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $groupId = $request->getRequiredBodyParam('id');
        $success = SimpleForms::$plugin->groups->deleteGroupById($groupId);

        Craft::$app->getSession()->setNotice(Craft::t('simple-forms', 'Group deleted.'));

        return $this->asJson([
            'success' => $success,
        ]);
    }
}
