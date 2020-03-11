<?php
namespace rias\simpleforms\services;

use craft\base\Component;
use craft\helpers\Db;
use rias\simpleforms\records\NoteRecord;

/**
 * simple-forms - Notes service
 */
class NotesService extends Component
{
    /**
     * Get a note by its ID.
     *
     * @param int $id
     *
     * @return array|\yii\db\ActiveRecord
     */
    public function getNoteById($id)
    {
        return NoteRecord::find()->where(Db::parseParam('id', $id))->one();
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
     * @return bool
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteNoteById($id)
    {
        /** @var NoteRecord $note */
        $note = $this->getNoteById($id);
        return $note->delete();
    }
}
