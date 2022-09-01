<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use ipl\Html\Html;

class ActionConfigForm extends UuidObjectForm
{
    protected $table = 'action';

    protected $mainProperties = ['label', 'implementation', 'enabled', 'filter', 'description'];

    protected function assemble()
    {
        $this->addElement('text', 'label', [
            'label'    => $this->translate('Label'),
            'required' => true
        ]);
        $this->addElement('select', 'enabled', [
            'label'       => $this->translate('Enabled'),
            'description' => $this->translate(
                'Whether the action is enabled and executed after an issue is created.'
            ),
            'options'     => [
                'y' => $this->translate('yes'),
                'n' => $this->translate('no')
            ],
            'required'    => true,
            'value'       => 'y'
        ]);
        $this->addElement('textarea', 'description', [
            'label' => $this->translate('Description'),
            'rows'  => 3
        ]);
        $this->addElement('text', 'filter', [
            'label'       => $this->translate('Filter'),
            'description' => Html::sprintf($this->translate(<<<'EOT'
Filter to restrict the execution of the action to certain topics.
A filter consists of filter expressions in the format %s.
%s can be any issue property,
and you can use the asterisk %s as a wildcard match placeholder in %s.
Issue attributes can be accessed via %s and custom variables via %s.
Expressions can be combined via %s (and) and %s (or),
and you can also use parentheses to group expressions.'
EOT,
            ),
                Html::tag('b', 'key=value'),
                Html::tag('b', 'key'),
                Html::tag('b', '*'),
                Html::tag('b', 'value'),
                Html::tag('b', 'attributes.key'),
                Html::tag('b', 'host.vars.os'),
                Html::tag('b', '&'),
                Html::tag('b', '|')
            ),
            'placeholder' => 'severity=critical&message=*SAP*&attributes.env=production&host.vars.os=Linux'
        ]);
        $this->addElement('select', 'implementation', [
            'label'    => $this->translate('Implementation'),
            'required' => true,
            'options'  => FormUtils::optionalEnum($this->registry->listImplementations()),
            'class'    => 'autosubmit',
        ]);
        $implementation = $this->getElementValue('implementation');
        if ($implementation === null) {
            return;
        }

        $this->getImplementationSettingsForm($implementation)->enhanceForm($this);
        $this->addButtons();
    }

    protected function getImplementationSettingsForm($implementation): FormExtension
    {
        /** @var string|Action $class IDE hint */
        $class = $this->registry->getClassName($implementation);

        return $class::getFormExtension();
    }
}
