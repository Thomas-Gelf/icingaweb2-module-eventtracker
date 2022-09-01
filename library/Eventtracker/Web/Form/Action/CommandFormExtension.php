<?php

namespace Icinga\Module\Eventtracker\Web\Form\Action;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use ipl\Html\Form;
use ipl\Html\Html;

class CommandFormExtension implements FormExtension
{
    use TranslationHelper;

    public function enhanceForm(Form $form)
    {
        $form->addElement('text', 'command', [
            'description' => Html::sprintf($this->translate(<<<'EOT'
You can use placeholders to have command arguments replaced by real values.
Placeholders are expressed with %s where %s can reference any issue property.
Issue attributes can be accessed via %s and custom variables via %s.
EOT
            ),
                Html::tag('b', '{placeholder}'),
                Html::tag('b', 'placeholder'),
                Html::tag('b', 'attributes.key'),
                Html::tag('b', 'host.vars.key')
            ),
            'label'       => $this->translate('Command line'),
            'placeholder' => '/path/to/command --severity {severity} --attribute {attributes.env} --os {host.vars.os}',
            'required'    => true
        ]);
    }
}
