<?php

namespace Icinga\Module\Eventtracker\Soap;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventracker\IcingaDb\CommandPipe;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\Engine\Action\SoapAction;
use Icinga\Module\Eventtracker\IdoMonitoring\IcingaCommandPipe;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use ipl\Orm\Model;

class SoapInteractiveActionForm extends Form
{
    use TranslationHelper;

    /** @var SoapAction */
    protected $soapAction;
    protected $object;

    /**
     * @param SoapAction $action
     * @param Model|MonitoredObject$object
     */
    public function __construct(SoapAction $action, $object)
    {
        $this->soapAction = $action;
        $this->object = $object;
    }

    protected function assemble()
    {
        $action = $this->soapAction;
        $settings = $action->getSettings();
        $client = $action->getSoapClient();
        $parser = SoapClientDefinitionParser::discover($client);
        $methodParams = $parser->getFlatMethodProperties($action->getMethodName());
        foreach ($methodParams as $name => $type) {
            [$l, $r] = explode('.', $name, 2);
            // Hint: There is something wrong with value handling in our form, dots didn't work
            $paramName = $l . '/' . $r;
            $value = $settings->get($paramName);

            if ($value) {
                $value = ConfigHelper::fillPlaceholders($value, $this->object, null, true);
            }
            $this->addElement('textarea', $paramName, [
                'label' => "$name",
                'rows'  => 1,
                'value' => $value,
            ]);
        }
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Submit')
        ]);
    }

    protected function onSuccess()
    {
        $values = [];
        foreach ($this->getValues() as $name => $value) {
            [$l, $r] = explode('/', $name, 2);
            $values[$l][$r] = $value;
        }
        $method = $this->soapAction->getMethodName();
        $result = $this->soapAction->getSoapClient()->$method(...$values);
        $username = Auth::getInstance()->getUser()->getUsername();
        if ($message = $this->soapAction->getSettings()->get('ackMessage')) {
            $message = ConfigHelper::fillPlaceholders($message, $result);
            if ($this->object instanceof Model) {
                $commandPipe = new CommandPipe();
                $commandPipe->acknowledgeObject($username, $message, $this->object);
            } else {
                $commandPipe = new IcingaCommandPipe();
                $commandPipe->acknowledgeObject($username, $message, $this->object);
            }
        }
    }
}
