<?php
/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace rias\simpleforms\events;

use craft\events\CancelableEvent;
use craft\mail\Message;
use rias\simpleforms\elements\Submission;
use yii\base\Event;

class EmailSubmissionEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var Submission|null The element model associated with the event.
     */
    public $submission;

    /**
     * @var Message
     */
    public $email;

    /** @var bool */
    public $success = true;
}
