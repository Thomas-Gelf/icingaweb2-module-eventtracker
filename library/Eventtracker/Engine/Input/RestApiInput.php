<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use Evenement\EventEmitterTrait;
use gipfl\Translation\StaticTranslator;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Engine\Input;
use Icinga\Module\Eventtracker\Engine\InputRunner;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Engine\SimpleTaskConstructor;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Web\Form\Input\RestApiFormExtension;
use React\EventLoop\LoopInterface;

class RestApiInput extends SimpleTaskConstructor implements Input
{
    use EventEmitterTrait;
    use SettingsProperty;

    protected ?string $token = null;

    public function applySettings(Settings $settings)
    {
        $this->token = $settings->getRequired('token');

        $this->setSettings($settings);
    }

    public static function getFormExtension(): FormExtension
    {
        return new RestApiFormExtension();
    }

    public static function getLabel()
    {
        return StaticTranslator::get()->translate('REST API Token');
    }

    public static function getDescription()
    {
        return StaticTranslator::get()->translate(
            'Accepts REST REQUESTS via POST eventracker/event'
        );
    }

    public function processObject($object)
    {
        $this->emit(InputRunner::ON_EVENT, [$object]);
    }

    public function run()
    {
        $this->start();
    }

    public function start()
    {
    }

    public function stop()
    {
    }

    public function pause()
    {
    }

    public function resume()
    {
    }
}
