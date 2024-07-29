<?php

namespace Icinga\Module\Eventtracker\Web\Form\Action;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Soap\SoapClient;
use Icinga\Module\Eventtracker\Soap\SoapClientDefinitionParser;
use ipl\Html\Form;
use ipl\Html\Html;

class SoapFormExtension implements FormExtension
{
    use TranslationHelper;

    public function enhanceForm(Form $form)
    {
        $form->addElement('text', 'url', [
            'label'       => $this->translate('SOAP (WSDL) Url'),
            'placeholder' => 'e.g. https://your-name.service-now.com/incident.do?WSDL',
            'required'    => true,
        ]);
        $form->addElement('text', 'username', [
            'label'       => $this->translate('Username'),
            'required'    => true,
        ]);
        $form->addElement('password', 'password', [
            'label'       => $this->translate('Password'),
            'required'    => true,
        ]);

        if ($url = $form->getValue('url')) {
            $username = $form->getValue('username');
            $password = $form->getValue('password');
            try {
                $client = new SoapClient($url, $username, $password);
                $parser = SoapClientDefinitionParser::discover($client);
                $methodNames = $parser->listMethodNames();
                $form->addElement('select', 'methodName', [
                    'label' => $this->translate('SOAP Method'),
                    'class' => 'autosubmit',
                    'options' => [
                        null => $this->translate('- please choose -'),
                    ] + array_combine($methodNames, $methodNames)
                ]);
                if ($methodName = $form->getValue('methodName')) {
                    $methodParams = $parser->getFlatMethodProperties($methodName);
                    if (! empty($methodParams)) {
                        $form->add(Hint::info(Html::sprintf(
                            $this->translate(
                                <<<'EOT'
You can pre-fill each of the following parameters. Placeholders are
expressed with %s where %s can reference any issue property.
Issue attributes can be accessed via %s and custom variables via %s.
Inline modifiers like in %s are allowed.
EOT
                            ),
                            Html::tag('b', '{placeholder}'),
                            Html::tag('b', 'placeholder'),
                            Html::tag('b', 'attributes.key'),
                            Html::tag('b', 'host.vars.key'),
                            Html::tag('b', '{host_name:lower}')
                        )));
                    }
                    foreach ($methodParams as $name => $type) {
                        list($l, $r) = explode('.', $name, 2);
                        // Hint: There is something wrong with value handling in our form, dots didn't work
                        $paramName = $l . '/' . $r;
                        $form->addElement('textarea', $paramName, [
                            'label' => "$name",
                            'rows' => 2,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $form->getElement('url')->addMessage($e->getMessage());
                 $form->add($e->getMessage() .  ' ' . $e->getFile() . ':' . $e->getLine());
            }

            $form->addElement('text', 'icingaActionHook', [
                'label'       => $this->translate('Icinga Action Hook'),
                'description' => $this->translate(
                    'Link label, in case you want to provide a Hook for interactive access to this method'
                    . ' in your Icinga Web monitoring/icingadb modules'
                ),
            ]);
        }
    }
}
