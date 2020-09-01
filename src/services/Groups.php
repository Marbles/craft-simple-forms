<?php

namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use Exception;
use rias\simpleforms\elements\Form;
use rias\simpleforms\records\FormGroupRecord;

/**
 * simple-forms - Groups service.
 */
class Groups extends Component
{
    private $_groupsById;
    private $_fetchedAllGroups = false;

    /**
     * Saves a group.
     *
     * @param FormGroupRecord $group
     *
     * @throws Exception
     *
     * @return bool
     */
    public function saveGroup(FormGroupRecord $group): bool
    {
        $groupRecord = $this->_getGroupRecord($group);
        $groupRecord->name = $group->name;

        if ($groupRecord->validate()) {
            $groupRecord->save(false);

            // Now that we have an ID, save it on the model & models
            if (!$group->id) {
                $group->id = $groupRecord->id;
            }

            return true;
        } else {
            $group->addErrors($groupRecord->getErrors());

            return false;
        }
    }

    /**
     * Deletes a group.
     *
     * @param $groupId
     *
     * @throws \yii\db\Exception
     *
     * @return bool
     */
    public function deleteGroupById($groupId)
    {
        $groupRecord = FormGroupRecord::findOne($groupId);

        if (!$groupRecord) {
            return false;
        }

        $affectedRows = Craft::$app->getDb()
            ->createCommand()
            ->delete('{{%simple-forms_formgroups}}', ['id' => $groupId])
            ->execute();

        return (bool) $affectedRows;
    }

    /**
     * Returns all groups.
     *
     * @param string|null $indexBy
     *
     * @return array
     */
    public function getAllFormGroups($indexBy = null)
    {
        if (!$this->_fetchedAllGroups) {
            $groupRecords = FormGroupRecord::find()
                ->orderBy(['name' => SORT_ASC])
                ->all();

            foreach ($groupRecords as $key => $groupRecord) {
                $groupRecords[$key] = new FormGroupRecord($groupRecord->toArray());
            }

            $this->_groupsById = $groupRecords;
            $this->_fetchedAllGroups = true;
        }

        if ($indexBy == 'id') {
            $groups = $this->_groupsById;
        } else {
            if (!$indexBy) {
                $groups = array_values($this->_groupsById);
            } else {
                $groups = [];
                foreach ($this->_groupsById as $group) {
                    $groups[$group->$indexBy] = $group;
                }
            }
        }

        return $groups;
    }

    /**
     * Get Forms by Group ID.
     *
     * @param int $groupId
     *
     * @return Form()
     */
    public function getFormsByGroupId($groupId)
    {
        $query = Craft::$app->getDb()
            ->createCommand()
            ->from('{{%simple-forms_formgroups}}')
            ->where('groupId=:groupId', ['groupId' => $groupId])
            ->order('name')
            ->all();

        foreach ($query as $key => $value) {
            $query[$key] = new Form($value);
        }

        return $query;
    }

    /**
     * Gets a form group record or creates a new one.
     *
     * @param FormGroupRecord $group
     *
     * @throws Exception
     *
     * @return FormGroupRecord|null|static
     */
    private function _getGroupRecord(FormGroupRecord $group)
    {
        if ($group->id) {
            $groupRecord = FormGroupRecord::findOne($group->id);

            if (!$groupRecord) {
                throw new Exception(
                    Craft::t('sprout-forms',
                        'No field group exists with the ID '.$group->id
                    )
                );
            }
        } else {
            $groupRecord = new FormGroupRecord();
        }

        return $groupRecord;
    }
}
