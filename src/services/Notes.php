<?php

namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use Exception;
use rias\simpleforms\records\NoteRecord;

/**
 * simple-forms - Notes service.
 */
class Notes extends Component
{
    /**
     * Get a note by its ID.
     *
     * @param int $id
     *
     * @throws Exception
     *
     * @return NoteRecord
     */
    public function getNoteById($id)
    {
        $note = NoteRecord::find()->where(Db::parseParam('id', $id))->one();

        if (!$note || !$note instanceof NoteRecord) {
            throw new Exception(Craft::t('simple-forms', 'No note exists with the ID “{id}”.', ['id' => $id]));
        }

        return $note;
    }

    /**
     * Get notes by submission ID.
     *
     * @param int $id
     *
     * @return array
     */
    public function getNotesBySubmissionId($id)
    {
        return NoteRecord::find()->orderBy('id desc')->where(Db::parseParam('submissionId', $id))->all();
    }

    /**
     * Save a note.
     *
     * @param NoteRecord $note
     *
     * @return bool
     */
    public function saveNote(NoteRecord $note)
    {
        return $note->save(false);
    }

    /**
     * Delete a note.
     *
     * @param int $id
     *
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     *
     * @return bool
     */
    public function deleteNoteById($id)
    {
        /** @var NoteRecord $note */
        $note = $this->getNoteById($id);

        return $note->delete() > 0;
    }
}
