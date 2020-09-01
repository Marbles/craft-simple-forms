<?php

namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use craft\web\View;
use GuzzleHttp\Client;
use rias\simpleforms\SimpleForms;

/**
 * simple-forms - reCAPTCHA service.
 */
class Recaptcha extends Component
{
    /**
     * Render a reCAPTCHA widget.
     *
     * @param bool $renderTwig
     *
     * @return bool|string
     */
    public function render()
    {
        // Get reCAPTCHA settings
        // Is reCAPTCHA enabled?
        if (SimpleForms::$plugin->getSettings()->googleRecaptchaEnabled) {
            // Plugin's default template path
            $templatePath = SimpleForms::$plugin->getBasePath().'/templates/_display/templates/_antispam/';

            // Build reCAPTCHA HTML
            $oldPath = Craft::$app->getView()->getTemplatesPath();
            Craft::$app->getView()->setTemplatesPath($templatePath);
            $html = Craft::$app->getView()->renderTemplate('recaptcha', [
                'siteKey' => SimpleForms::$plugin->getSettings()->googleRecaptchaSiteKey,
            ]);

            // Reset templates path
            Craft::$app->getView()->setTemplatesPath($oldPath);

            // Include Google's reCAPTCHA API
            Craft::$app->getView()->registerJsFile('https://www.google.com/recaptcha/api.js', [
                'position' => View::POS_HEAD,
            ]);

            // Parse widget
            return new \Twig_Markup($html, Craft::$app->getView()->getTwig()->getCharset());
        }

        return false;
    }

    /**
     * Verify a reCAPTCHA submission.
     *
     * @return bool
     */
    public function verify()
    {
        // Get reCAPTCHA value
        $captcha = Craft::$app->getRequest()->getBodyParam('g-recaptcha-response');

        // Get reCAPTCHA secret key
        $secretKey = SimpleForms::$plugin->getSettings()->googleRecaptchaSecretKey;
        if (!$secretKey) {
            return false;
        }

        // Google API parameters
        $params = [
            'secret'   => $secretKey,
            'response' => $captcha,
        ];

        // Set request
        $client = new Client();
        $result = $client->post('https://www.google.com/recaptcha/api/siteverify', [
            'form_params' => $params,
        ]);

        // Handle response
        if ($result->getStatusCode() == 200) {
            $json = json_decode((string) $result->getBody());

            if ($json->success) {
                return true;
            }
        }

        return false;
    }
}
