<?php

/**
 * AmForms Default Configuration.
 */
return [
    /** General */
    'pluginName' => 'Forms',
    'quietErrors' => false,
    'fieldsPerSet' => 8,
    'bccEmailAddress' => '',

    /** Submissions */
    'cleanUpSubmissions' => true,
    'cleanUpSubmissionsFrom' => '-4 weeks',

    /** Export */
    'delimiter' => ';',
    'exportRowsPerSet' => 50,
    'ignoreMatrixFieldAndBlockNames' => false,
    'ignoreMatrixMultipleRows' => false,

    /** Antispam */
    'honeypotEnabled' => true,
    'honeypotName' => 'yourssince1615',
    'timeCheckEnabled' => true,
    'minimumTimeInSeconds' => 3,
    'duplicateCheckEnabled' => true,
    'originCheckEnabled' => true,

    /** Recaptcha */
    'googleRecaptchaEnabled' => false,
    'googleRecaptchaSiteKey' => '',
    'googleRecaptchaSecretKey' => '',

    /** Templates */
    'formTemplate' => '',
    'tabTemplate' => '',
    'fieldTemplate' => '',
    'notificationTemplate' => '',
    'confirmationTemplate' => '',

    /** Fields */
    'fields' => [
        [
            'name' => 'Full name',
            'type' => craft\fields\PlainText::class,
        ],
        [
            'name' => 'First name',
            'type' => craft\fields\PlainText::class,
        ],
        [
            'name' => 'Last name',
            'type' => craft\fields\PlainText::class,
        ],
        [
            'name' => 'Website',
            'type' => craft\fields\PlainText::class,
        ],
        [
            'name' => 'Email address',
            'type' => \craft\fields\Email::class,
        ],
        [
            'name' => 'Telephone number',
            'type' => craft\fields\PlainText::class,
        ],
        [
            'name' => 'Mobile number',
            'type' => craft\fields\PlainText::class,
        ],
        [
            'name' => 'Comment',
            'type' => craft\fields\PlainText::class,
            'settings' => [
                'multiline'   => 1,
                'initialRows' => 4,
            ],
        ],
        [
            'name' => 'Reaction',
            'type' => craft\fields\PlainText::class,
            'settings' => [
                'multiline'   => 1,
                'initialRows' => 4,
            ],
        ],
        [
            'name' => 'Image',
            'type' => \craft\fields\Assets::class,
            'translatable' => false,
            'settings' => [
                'restrictFiles' => 1,
                'allowedKinds' => ['image'],
                'sources' => ['folder:1'],
                'singleUploadLocationSource' => '1',
                'defaultUploadLocationSource' => '1',
                'limit' => 1,
            ],
        ],
        [
            'name' => 'File',
            'type' => \craft\fields\Assets::class,
            'translatable' => false,
            'settings' => [
                'sources' => ['folder:1'],
                'singleUploadLocationSource' => '1',
                'defaultUploadLocationSource' => '1',
                'limit' => 1,
            ],
        ],
    ],
];
