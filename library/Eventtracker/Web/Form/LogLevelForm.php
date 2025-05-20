<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form\Feature\NextConfirmCancel;
use gipfl\Web\InlineForm;
use Icinga\Module\Eventtracker\Daemon\RemoteClient;
use ipl\Html\FormElement\SelectElement;
use Psr\Log\LogLevel;

use function Clue\React\Block\await;

class LogLevelForm extends InlineForm
{
    use TranslationHelper;

    protected RemoteClient $client;
    protected bool $talkedToSocket = false;

    public function __construct(RemoteClient $client)
    {
        $this->client = $client;
    }

    public function talkedToSocket(): ?bool
    {
        return $this->talkedToSocket;
    }

    protected function assemble()
    {
        try {
            $currentLevel = await($this->client->request('logger.getLogLevel'));
            $this->talkedToSocket = true;
        } catch (\Exception $e) {
            $this->talkedToSocket = false;
            return;
        }

        $toggle = new NextConfirmCancel(
            NextConfirmCancel::buttonNext($currentLevel ?: $this->translate('unspecified'), [
                'title' => $this->translate('Click to change the current Daemon Log Level')
            ]),
            NextConfirmCancel::buttonConfirm($this->translate('Set')),
            NextConfirmCancel::buttonCancel($this->translate('Cancel'))
        );
        $toggle->showWithConfirm(new SelectElement('log_level', [
            'options'  => [null => $this->translate('- please choose -')] + $this->listLogLevels(),
            'required' => true,
            'value'    => $currentLevel,
        ]));
        $toggle->addToForm($this);
    }

    protected function onSuccess()
    {
        await($this->client->request('logger.setLogLevel', [
            'level' => $this->getValue('log_level')
        ]));
    }

    protected function listLogLevels()
    {
        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];

        return array_combine($levels, $levels);
    }
}
