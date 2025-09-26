<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form\Feature\NextConfirmCancel;
use gipfl\Web\InlineForm;
use Icinga\Module\Eventtracker\Modifier\ModifierRuleStore;

class DropChannelRuleChangesForm extends InlineForm
{
    use TranslationHelper;

    private ModifierRuleStore $ruleStore;

    public function __construct(ModifierRuleStore $ruleStore)
    {
        $this->ruleStore = $ruleStore;
    }

    protected function assemble()
    {
        (new NextConfirmCancel(
            NextConfirmCancel::buttonNext($this->translate('Forget my changes'), [
                'title' => $this->translate('Click to drop all unsaved changes applied to this channel'),
            ]),
            NextConfirmCancel::buttonConfirm($this->translate('Yes, please drop my changes')),
            NextConfirmCancel::buttonCancel($this->translate('Cancel'))
        ))->addToForm($this);
    }

    protected function onSuccess()
    {
        $this->ruleStore->deleteSessionRules();
    }
}
