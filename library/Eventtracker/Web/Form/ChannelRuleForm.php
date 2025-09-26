<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Exception;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\Web\Form\Decorator\DdDtDecorator;
use Icinga\Module\Eventtracker\Modifier\Modifier;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\ModifierRegistry;
use Icinga\Module\Eventtracker\Modifier\ModifierRuleStore;
use Icinga\Module\Eventtracker\Modifier\Settings;
use ipl\Html\FormElement\SubmitElement;
use RuntimeException;

class ChannelRuleForm extends Form
{
    use TranslationHelper;

    protected ?Modifier $modifier = null;
    private ModifierRuleStore $modifierRuleStore;
    private ModifierChain $modifierChain;
    private ?int $row = null;

    protected SubmitElement $cancelButton;

    public function __construct(ModifierRuleStore $modifierRuleStore)
    {
        $this->modifierRuleStore = $modifierRuleStore;
        $this->modifierChain = $modifierRuleStore->getRules();
    }
    public function getModifier(): Modifier
    {
        if ($this->modifier === null) {
            throw new RuntimeException('Form has no Modifier');
        }

        return $this->modifier;
    }

    public function editRow(int $row, string $compareChecksum)
    {
        if ($this->modifierChain->getShortChecksum() === $compareChecksum) {
            $this->row = $row;
            $modifier = $this->modifierChain->getModifiers()[$row];
            $this->populate(['modifyProperty' => $modifier[0], 'modifierImplementation' => $modifier[1]->getName()]);
            $this->populate((array) $modifier[1]->getSettings()->jsonSerialize());
        } else {
            throw new Exception(
                'Checksum doesn\'t not match checksum from url: '
                . $compareChecksum . ' != ' . $this->modifierChain->getShortChecksum()
            );
        }
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
        $this->addCancelButton();
    }

    public function onSuccess()
    {
        $class = ModifierRegistry::getClassName($this->getValue('modifierImplementation'));
        $this->modifier = new $class(Settings::fromSerialization($this->getValues()));

        if ($this->row !== null) {
            $this->modifierChain->replaceModifier($this->modifier, $this->getPropertyName(), $this->row);
            $this->modifierRuleStore->setModifierRules($this->modifierChain);
        }
    }
    protected function addCancelButton()
    {
        $button = $this->createElement('submit', 'delete', [
            'label' => $this->translate('Cancel'),
            'formnovalidate' => true,
        ]);
        assert($button instanceof SubmitElement);
        $this->cancelButton = $button;
        $submit = $this->getElement('submit');
        $decorator = $submit->getWrapper();
        assert($decorator instanceof DdDtDecorator);
        $decorator->dd()->add($button);
        $this->registerElement($button);
    }

    public function hasBeenCancelled(): bool
    {
        return $this->cancelButton->hasBeenPressed();
    }
}
