<?php
/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace rias\simpleforms\events;

use craft\events\CancelableEvent;
use rias\simpleforms\elements\Submission;
use yii\base\Event;

class SubmissionEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var Submission|null The element model associated with the event.
     */
    public $submission;

    /**
     * @var bool Whether the element is brand new
     */
    public $isNew = false;
}
