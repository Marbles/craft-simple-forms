<?php
namespace rias\simpleforms\services;

use Craft;
use craft\base\Component;
use rias\simpleforms\events\AntiSpamEvent;
use rias\simpleforms\SimpleForms;
use yii\base\Event;

/**
 * simple-forms - AntiSpam service
 */
class AntiSpamService extends Component
{
    const EVENT_VERIFY_ANTI_SPAM = 'onVerifyAntiSpam';

    /**
     * Check whether a form is marked as no spam.
     *
     * @param string $formHandle
     *
     * @return bool
     */
    public function isMarkedAsNoSpam($formHandle)
    {
        return $this->_verifyToken($formHandle);
    }

    /**
     * Set a form marked as no spam.
     *
     * @param string $formHandle
     */
    public function setMarkedAsNoSpam($formHandle)
    {
        $this->_setToken($formHandle);
    }

    /**
     * Render AntiSpam functionality.
     *
     * @return bool|string
     */
    public function render()
    {
        $rendered = [];

        // Plugin's default template path
        $oldPath = Craft::$app->getView()->getTemplatesPath();
        $templatePath = SimpleForms::$plugin->getBasePath() . '/templates/_display/templates/_antispam/';
        Craft::$app->getView()->setTemplatesPath($templatePath);

        // Honeypot enabled?
        if (SimpleForms::$plugin->getSettings()->honeypotEnabled) {
            if(($result = $this->_renderHoneypot(SimpleForms::$plugin->getSettings()->honeypotName)) !== false) {
                $rendered[] = $result;
            }
        }

        // Time check enabled?
        if (SimpleForms::$plugin->getSettings()->timeCheckEnabled) {
            if(($result = $this->_renderTime(SimpleForms::$plugin->getSettings()->minimumTimeInSeconds)) !== false) {
                $rendered[] = $result;
            }
        }

        // Duplicate check enabled?
        if (SimpleForms::$plugin->getSettings()->duplicateCheckEnabled) {
            $this->_setToken('duplicate');
        }

        // Origin check enabled?
        if (SimpleForms::$plugin->getSettings()->originCheckEnabled) {
            if(($result = $this->_renderOrigin()) !== false) {
                $rendered[] = $result;
            }
        }

        // Reset templates path
        Craft::$app->getView()->setTemplatesPath($oldPath);

        // Parse antispam protection
        if (count($rendered)) {
            return new \Twig_Markup(implode("\n", $rendered), Craft::$app->getView()->getTwig()->getCharset());
        }
    }

    /**
     * Verify AntiSpam submission.
     *
     * @param string $formHandle
     *
     * @return bool
     */
    public function verify($formHandle)
    {
        // Get AntiSpam settings
        // Honeypot enabled?
        if (SimpleForms::$plugin->getSettings()->honeypotEnabled) {
            if (! $this->_verifyHoneypot(SimpleForms::$plugin->getSettings()->honeypotName)) {
                return false;
            }
        }

        // Time check enabled?
        if (SimpleForms::$plugin->getSettings()->timeCheckEnabled && ! $this->isMarkedAsNoSpam($formHandle)) {
            if (! $this->_verifyTime(SimpleForms::$plugin->getSettings()->minimumTimeInSeconds)) {
                return false;
            }
        }

        // Duplicate check enabled?
        if (SimpleForms::$plugin->getSettings()->duplicateCheckEnabled) {
            if (! $this->_verifyToken('duplicate')) {
                return false;
            }
        }

        // Origin check enabled?
        if (SimpleForms::$plugin->getSettings()->originCheckEnabled) {
            if (! $this->_verifyOrigin()) {
                return false;
            }
        }

        // Fire an 'onVerifyAntispam' event
        $event = new AntiSpamEvent([
            'formHandle' => $formHandle,
            'performAction' => true,
        ]);
        $this->trigger(self::EVENT_VERIFY_ANTI_SPAM, $event);

        // Is the event letting us now it was still spam?
        if (! $event->performAction) {
            return false;
        }

        // We didn't encounter any problems
        return true;
    }

    /**
     * Render honeypot.
     *
     * @param string $fieldName
     *
     * @return bool|string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    private function _renderHoneypot($fieldName)
    {
        // Validate field name
        if (empty($fieldName)) {
            return false;
        }

        // Render HTML
        return Craft::$app->getView()->renderTemplate('honeypot', array(
            'fieldName' => $fieldName
        ));
    }

    /**
     * Verify honeypot submission.
     *
     * @param string $fieldName
     *
     * @return bool
     */
    private function _verifyHoneypot($fieldName)
    {
        // Validate field name
        if (empty($fieldName)) {
            return false;
        }

        // Validate submission
        if (Craft::$app->request->getBodyParam($fieldName)) {
            return false;
        }
        return true;
    }

    /**
     * Render time.
     *
     * @param int $seconds
     *
     * @return bool|string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    private function _renderTime($seconds)
    {
        // Validate seconds
        if (empty($seconds) || ! is_numeric($seconds) || $seconds <= 0) {
            return false;
        }

        // Render HTML
        return Craft::$app->getView()->renderTemplate('time', array(
            'time' => time()
        ));
    }

    /**
     * Verify time submission.
     *
     * @param int $seconds
     *
     * @return bool|string
     */
    private function _verifyTime($seconds)
    {
        // Validate seconds
        if (empty($seconds) || ! is_numeric($seconds) || $seconds <= 0) {
            return false;
        }

        // Validate submission
        $currentTime = time();
        $renderTime  = (int) Craft::$app->getRequest()->getBodyParam('__UATIME', time());
        $difference  = ($currentTime - $renderTime);
        $minimumTime = (int) $seconds;

        return (bool) ($difference > $minimumTime);
    }

    /**
     * Render origin.
     *
     * @return string
     */
    private function _renderOrigin()
    {
        // Render HTML
        return Craft::$app->getView()->renderTemplate('origin', array(
            'domain'    => $this->_getHash(Craft::$app->getRequest()->getHostInfo()),
            'userAgent' => $this->_getHash(Craft::$app->getRequest()->getUserAgent())
        ));
    }

    /**
     * Verify origin submission.
     *
     * @return bool
     */
    private function _verifyOrigin()
    {
        $renderDomain = Craft::$app->getRequest()->getBodyParam('__UAHOME');
        $renderUserAgent = Craft::$app->getRequest()->getBodyParam('__UAHASH');

        $domain = $this->_getHash(Craft::$app->getRequest()->getHostInfo());
        $userAgent = $this->_getHash(Craft::$app->getRequest()->getUserAgent());

        if (! $renderDomain || $renderDomain != $domain) {
            return false;
        }
        elseif (! $renderUserAgent || $renderUserAgent != $userAgent) {
            return false;
        }

        return true;
    }

    /**
     * Set token.
     */
    private function _setToken($suffix)
    {
        // Create a unique token
        $token = uniqid();

        // Create session variable
        Craft::$app->getSession()->set('simpleFormsToken_' . $suffix, $token);
    }

    /**
     * Verify token.
     *
     * @return bool
     */
    private function _verifyToken($suffix)
    {
        $tokenName = 'simpleFormsToken_' . $suffix;
        if (Craft::$app->getSession()->get($tokenName)) {
            // We got a token, so this is a valid submission
            Craft::$app->getSession()->remove($tokenName);
            return true;
        }

        return false;
    }

    /**
     * Create a hash from string.
     *
     * @param string $str
     *
     * @return string
     */
    private function _getHash($str)
    {
        return md5(sha1($str));
    }
}
