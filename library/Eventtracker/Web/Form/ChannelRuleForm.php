<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\Web\InlineForm;
use Icinga\Chart\Inline\Inline;
use Icinga\Module\Eventtracker\Modifier\Modifier;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\ModifierRegistry;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Web\Form\Decorator\Autosubmit;


class ChannelRuleForm extends Form
{
    use TranslationHelper;

    protected ?Modifier $modifier = null;

    public function getModifier(): Modifier
    {
        if ($this->modifier === null) {
            throw new \RuntimeException('Form has no Modifier');
        }

        return $this->modifier;
    }

    public function getPropertyName(): string
    {
        return $this->getValue('modifyProperty');
    }

    protected function assemble()
    {
        $this->addElement('text', 'modifyProperty', [
            'label' => $this->translate('Property'),
            'description' => $this->translate('Event property, that should be modified'),
            'required' => true,
            'ignore' => true,
        ]);

        /** @var array<string, class-string<Modifier>> $implementations */
        $implementations = ModifierRegistry::getInstance()->listModifiers();
        $implementationNames = array_keys($implementations);
        $enum = array_combine($implementationNames, $implementationNames);
        $this->addElement('select', 'modifierImplementation', [
            'label' => $this->translate('Implementation'),
            'required' => true,
            'ignore' => true,
            'class' => 'autosubmit',
            'options' => [null => $this->translate('- please choose -')] + $enum,
        ]);
        if ($implementation = $this->getValue('modifierImplementation')) {
            $class = $implementations[$implementation];
            $class::extendSettingsForm($this);
        }
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Store'),
        ]);
    }

    public function onSuccess()
    {
        $class = ModifierRegistry::getClassName($this->getValue('modifierImplementation'));
        $this->modifier = new $class(Settings::fromSerialization($this->getValues()));
    }
}
