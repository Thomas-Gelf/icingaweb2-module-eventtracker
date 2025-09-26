<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\InlineForm;
use Icinga\Module\Eventtracker\Modifier\ModifierRuleStore;

class SaveChannelRulesChangesForm extends InlineForm
{
    use TranslationHelper;

    private ModifierRuleStore $ruleStore;

    public function __construct(ModifierRuleStore $ruleStore)
    {
        $this->ruleStore = $ruleStore;
    }

    protected function assemble()
    {
        $this->addElement('submit', 'submit', ['label' => $this->translate('Save')]);
    }

    protected function onSuccess()
    {
        $this->ruleStore->storeRules();
    }
}
