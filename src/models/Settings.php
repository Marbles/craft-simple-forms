<?php

namespace rias\simpleforms\models;

use craft\base\Model;

class Settings extends Model
{
    /** General */
    public $pluginName = 'Forms';
    public $quietErrors = false;
    public $fieldsPerSet = 8;
    public $bccEmailAddress = '';
    public $useInjectedCsrfInput = false;

    /** Submissions */
    public $cleanUpSubmissions = true;
    public $cleanUpSubmissionsFrom = '-4 weeks';

    /** Export */
    public $delimiter = ';';
    public $exportRowsPerSet = 50;
    public $ignoreMatrixFieldAndBlockNames = false;
    public $ignoreMatrixMultipleRows = false;

    /** Antispam */
    public $honeypotEnabled = true;
    public $honeypotName = 'yourssince1615';
    public $timeCheckEnabled = true;
    public $minimumTimeInSeconds = 3;
    public $duplicateCheckEnabled = true;
    public $originCheckEnabled = true;

    /** Recaptcha */
    public $googleRecaptchaEnabled = false;
    public $googleRecaptchaSiteKey = '';
    public $googleRecaptchaSecretKey = '';

    /** Templates */
    public $formTemplate = '';
    public $tabTemplate = '';
    public $fieldTemplate = '';
    public $notificationTemplate = '';
    public $confirmationTemplate = '';

    /** Fields */
    public $fields = [];
}
