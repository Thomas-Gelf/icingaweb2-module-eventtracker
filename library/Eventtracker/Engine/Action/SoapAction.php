<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use Evenement\EventEmitterTrait;
use gipfl\Translation\StaticTranslator;
use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Engine\SimpleTaskConstructor;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Soap\SoapClient;
use Icinga\Module\Eventtracker\Web\Form\Action\SoapFormExtension;
use React\Promise\PromiseInterface;

use function React\Promise\reject;

class SoapAction extends SimpleTaskConstructor implements Action
{
    use ActionProperties;
    use DummyTaskActions;
    use EventEmitterTrait;
    use SettingsProperty;

    protected $paused = true;

    /** @var string */
    protected $url;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var string */
    protected $methodName;

    /** @var ?string */
    protected $ackMessage;

    /** @var Settings */
    protected $serviceSettings;

    public function applySettings(Settings $settings)
    {
        $this->setSettings($settings);
        $settings = Settings::fromSerialization($settings->jsonSerialize());
        $this->url = $settings->shiftRequired('url');
        $this->username = $settings->shiftRequired('username');
        $this->password = $settings->shiftRequired('password');
        $this->methodName = $settings->shiftRequired('methodName');
        $this->ackMessage = $settings->shift('ackMessage');
        $this->serviceSettings = $settings;
    }

    public static function getFormExtension(): FormExtension
    {
        return new SoapFormExtension();
    }

    public static function getLabel()
    {
        return StaticTranslator::get()->translate('SOAP');
    }

    public static function getDescription()
    {
        return StaticTranslator::get()->translate(
            'Trigger a SOAP operation'
        );
    }

    public function process(Issue $issue): PromiseInterface
    {
        return reject(new \RuntimeException('Not yet'));
    }

    public function getSoapClient(): SoapClient
    {
        return new SoapClient($this->url, $this->username, $this->password);
    }

    public function getServiceSettings(): Settings
    {
        return $this->serviceSettings;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getAckMessage(): ?string
    {
        return $this->ackMessage;
    }

    protected function sendIssue(Issue $issue)
    {
        throw new \RuntimeException('The SoapAction does not trigger automated issues');
    }
}
