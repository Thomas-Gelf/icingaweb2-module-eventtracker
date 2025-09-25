<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use ipl\Html\Html;

class CombineProperties extends BaseModifier
{
    protected static ?string $name = 'Combine multiple properties';

    public function transform($object, string $propertyName)
    {
        return ObjectUtils::fillVariables($this->settings->getRequired('pattern'), $object);
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('text', 'pattern', [
            'label'       => $form->translate('Pattern'),
            'required'    => false,
            'description' => Html::sprintf($form->translate(
                'This pattern will be evaluated, and variables like %s'
                . ' will be filled accordingly. A typical use-case is generating'
                . ' unique service identifiers via %s in case your'
                . ' data source doesn\'t allow you to ship such. The chosen "property"'
                . ' has no effect here and will be ignored.'
            ), Html::tag('strong', '${some_column}'), Html::tag('strong', '${host}!${service}')),
        ]);
    }
}
