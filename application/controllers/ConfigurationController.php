<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Web\Form;
use gipfl\Json\JsonDecodeException;
use gipfl\Json\JsonString;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\ModifierUtils;
use Icinga\Module\Eventtracker\Web\Form\ChannelConfigForm;
use Icinga\Module\Eventtracker\Web\Form\InputConfigForm;
use Icinga\Module\Eventtracker\Engine\Input\InputRegistry;
use Icinga\Module\Eventtracker\Web\Form\UuidObjectForm;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;
use Icinga\Module\Eventtracker\Web\Table\ConfiguredChannelsTable;
use Icinga\Module\Eventtracker\Web\Table\ConfiguredInputsTable;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

class ConfigurationController extends Controller
{
    protected $variants;

    protected $variant;

    public function init()
    {
        $this->variants = [
            'inputs' => [
                'singular' => $this->translate('Input'),
                'plural'   => $this->translate('Inputs'),
                'table'    => 'input',
                'list_url' => 'eventtracker/configuration/inputs',
                'url'      => 'eventtracker/configuration/input',
                'table_class' => ConfiguredInputsTable::class,
                'form_class'  => InputConfigForm::class,
            ],
            'channels' => [
                'singular' => $this->translate('Channel'),
                'plural'   => $this->translate('Channels'),
                'table'    => 'channel',
                'list_url' => 'eventtracker/configuration/channels',
                'url'      => 'eventtracker/configuration/channel',
                'table_class' => ConfiguredChannelsTable::class,
                'form_class'  => ChannelConfigForm::class,
            ],
        ];
    }

    protected function variant($key)
    {
        return $this->variants[$this->variant][$key];
    }

    public function inputsAction()
    {
        $this->variant = 'inputs';
        $this->showList();
    }

    public function inputAction()
    {
        $this->variant = 'inputs';
        $this->showForm();
    }

    public function channelsAction()
    {
        $this->variant = 'channels';
        $this->showList();
    }

    public function channelAction()
    {
        $this->variant = 'channels';
        $form = $this->showForm();
        if ($rules = $form->getElementValue('rules')) {
            $this->showRules($rules);
        }
    }

    protected function showRules($rules)
    {
        try {
            $modifiers = ModifierChain::fromSerialization(JsonString::decode($rules));
        } catch (JsonDecodeException $e) {
            return;
        }
        $info = Html::tag('ul');
        foreach ($modifiers->getModifiers() as list($propertyName, $modifier)) {
            $info->add(Html::tag('li', ModifierUtils::describeModifier($propertyName, $modifier)));
        }
        $this->content()->add([
            Html::tag('h3', $this->translate('Configured Rules')),
            $info
        ]);
    }

    protected function showList()
    {
        $this->addTitle(sprintf($this->translate('Configured %s'), $this->variant('plural')));
        $this->tabForList($this->variant);
        $this->actions()->add(Link::create($this->translate('Add'), $this->variant('url'), null, [
            'data-base-target' => '_next',
            'class' => 'icon-plus',
        ]));
        /** @var string|BaseTable $class IDE Hint*/
        $class = $this->variant('table_class');
        $table = new $class($this->db());
        if ($table->count() > 0) {
            $this->content()->add($table);
        } else {
            $this->content()->add(Hint::info(sprintf(
                $this->translate('Please configure your first %s'),
                $this->variant('singular')
            )));
        }
    }

    protected function showForm()
    {
        $store = new ConfigStore($this->db());
        /** @var string|Form $formClass IDE hint */
        $formClass = $this->variant('form_class');
        $form = new $formClass(new InputRegistry(), $store);
        $objectType = $this->variant('singular');
        if ($uuid = $this->params->get('uuid')) {
            $uuid = Uuid::fromString($uuid);
            $object = $store->fetchObject($this->variant('table'), $uuid);
            if (isset($object->settings)) {
                foreach ($object->settings as $key => $value) {
                    $object->$key = $value;
                }
                unset($object->settings);
            }
            $form->populate((array) $object);
            $this->addTitle("$objectType: %s", $object->label);
        } else {
            $this->addTitle($this->translate('Define a new %s'), $objectType);
        }
        $this->addSingleTab(sprintf($this->translate('%s Configuration'), $objectType));
        $form->on($form::ON_SUCCESS, function (UuidObjectForm $form) {
            $this->redirectNow(Url::fromPath($this->variant('url'), [
                'uuid' => $form->getUuid()->toString()
            ]));
        });
        $form->handleRequest($this->getServerRequest());
        if ($form->hasBeenDeleted()) {
            $this->redirectNow($this->variant('list_url'));
        }
        $this->content()->add($form);

        return $form;
    }

    protected function tabForList($name)
    {
        $this->tabs()->add('inputs', [
            'label' => $this->translate('Inputs'),
            'url'   => 'eventtracker/configuration/inputs',
        ])->add('channels', [
            'label' => $this->translate('Channels'),
            'url'   => 'eventtracker/configuration/channels',
        ])->activate($name);
    }
}
