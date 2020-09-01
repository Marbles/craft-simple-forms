<?php

namespace rias\simpleforms\events;

use yii\base\Event;

class AntiSpamEvent extends Event
{
    /** @var string */
    public $formHandle;

    /** @var bool */
    public $performAction = true;
}
