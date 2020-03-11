<?php

/**
 * Width Fieldtype plugin for Craft CMS 3.x.
 *
 * Brings back the Width fieldtype from Craft 2
 *
 * @link      https://rias.be
 *
 * @copyright Copyright (c) 2017 Rias
 */

namespace rias\simpleforms\assetbundles\simpleforms;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class SimpleFormsAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * Initializes the bundle.
     */
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = '@rias/simpleforms/assetbundles/simpleforms/';
        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];
        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'js/AdminTable.js',
            'js/FieldLayoutDesigner.js',
            'js/FormAttributes.js',
        ];

        $this->css = [
            'css/datepicker.css',
            'css/FormAttributes.css',
            'css/timepicker.css',
        ];

        parent::init();
    }
}
