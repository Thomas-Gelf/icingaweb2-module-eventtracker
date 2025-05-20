<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form\Feature\NextConfirmCancel;
use gipfl\Web\InlineForm;
use Icinga\Module\Eventtracker\Daemon\RemoteClient;
use function Clue\React\Block\await;

class RestartDaemonForm extends InlineForm
{
    use TranslationHelper;

    protected RemoteClient $client;

    public function __construct(RemoteClient $client)
    {
        $this->client = $client;
    }

    protected function assemble()
    {
        (new NextConfirmCancel(
            NextConfirmCancel::buttonNext($this->translate('Restart'), [
                'title' => $this->translate('Click to restart the vSphereDB background daemon'),
            ]),
            NextConfirmCancel::buttonConfirm($this->translate('Yes, please restart')),
            NextConfirmCancel::buttonCancel($this->translate('Cancel'))
        ))->addToForm($this);
    }

    protected function onSuccess()
    {
        await($this->client->request('process.restart'));
    }
}
