<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Json\JsonDecodeException;
use gipfl\Json\JsonString;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Action\ActionRegistry;
use Icinga\Module\Eventtracker\Engine\Bucket\BucketRegistry;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRule;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\ModifierUtils;
use Icinga\Module\Eventtracker\Web\Form\ActionConfigForm;
use Icinga\Module\Eventtracker\Web\Form\ApiTokenForm;
use Icinga\Module\Eventtracker\Web\Form\BucketConfigForm;
use Icinga\Module\Eventtracker\Web\Form\ChannelConfigForm;
use Icinga\Module\Eventtracker\Web\Form\DowntimeForm;
use Icinga\Module\Eventtracker\Web\Form\InputConfigForm;
use Icinga\Module\Eventtracker\Engine\Input\InputRegistry;
use Icinga\Module\Eventtracker\Web\Form\UuidObjectForm;
use Icinga\Module\Eventtracker\Web\Table\ApiTokensTable;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;
use Icinga\Module\Eventtracker\Web\Table\ChannelRulesTable;
use Icinga\Module\Eventtracker\Web\Table\ConfiguredBucketsTable;
use Icinga\Module\Eventtracker\Web\Table\ConfiguredChannelsTable;
use Icinga\Module\Eventtracker\Web\Table\ConfiguredHostListsTable;
use Icinga\Module\Eventtracker\Web\Table\ConfiguredInputsTable;
use Icinga\Module\Eventtracker\Web\Table\ConfiguredActionsTable;
use Icinga\Module\Eventtracker\Web\Table\DowntimeRulesTable;
use Icinga\Module\Eventtracker\Web\Table\DowntimeScheduleTable;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ConfigurationController extends Controller
{
    protected $variants;

    protected $variant;

    /** @var ConfigStore */
    protected $store;

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
                'registry'    => InputRegistry::class,
            ],
            'channels' => [
                'singular' => $this->translate('Channel'),
                'plural'   => $this->translate('Channels'),
                'table'    => 'channel',
                'list_url' => 'eventtracker/configuration/channels',
                'url'      => 'eventtracker/configuration/channel',
                'table_class' => ConfiguredChannelsTable::class,
                'form_class'  => ChannelConfigForm::class,
                'registry'    => InputRegistry::class,
            ],
            'apitokens' => [
                'singular' => $this->translate('API Token'),
                'plural'   => $this->translate('Api Tokens'),
                'table'    => 'api_token',
                'list_url' => 'eventtracker/configuration/apitokens',
                'url'      => 'eventtracker/configuration/apitoken',
                'table_class' => ApiTokensTable::class,
                'form_class'  => ApiTokenForm::class,
                'registry'    => InputRegistry::class,
            ],
            'actions' => [
                'singular' => $this->translate('Action'),
                'plural'   => $this->translate('Actions'),
                'table'    => 'action',
                'list_url' => 'eventtracker/configuration/actions',
                'url'      => 'eventtracker/configuration/action',
                'table_class' => ConfiguredActionsTable::class,
                'form_class'  => ActionConfigForm::class,
                'registry'    => ActionRegistry::class,
            ],
            'buckets' => [
                'singular' => $this->translate('Bucket'),
                'plural'   => $this->translate('Buckets'),
                'table'    => 'bucket',
                'list_url' => 'eventtracker/configuration/buckets',
                'url'      => 'eventtracker/configuration/bucket',
                'table_class' => ConfiguredBucketsTable::class,
                'form_class'  => BucketConfigForm::class,
                'registry'    => BucketRegistry::class,
            ],
            'downtimes' => [
                'singular' => $this->translate('Downtime'),
                'plural'   => $this->translate('Downtimes'),
                'table'    => 'downtime_rule',
                'list_url' => 'eventtracker/configuration/downtimes',
                'url'      => 'eventtracker/configuration/downtime',
                'table_class' => DowntimeRulesTable::class,
                'form_class'  => DowntimeForm::class,
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
        $this->addObjectTab();
        $this->content()->add($this->getForm());
    }

    public function apitokensAction()
    {
        $this->variant = 'apitokens';
        $this->showList();
    }

    public function apitokenAction()
    {
        $this->variant = 'apitokens';
        $this->addObjectTab();
        $this->content()->add($this->getForm());
    }

    public function channelsAction()
    {
        $this->variant = 'channels';
        $this->showList();
    }

    public function channelAction()
    {
        $this->variant = 'channels';
        $this->addObjectTab();
        $form = $this->getForm();
        $this->content()->add($form);
        if ($rules = $form->getElementValue('rules')) {
            $this->showRules($rules);
        }
    }

    public function actionsAction()
    {
        $this->variant = 'actions';
        $this->showList();
    }

    public function actionAction()
    {
        $this->variant = 'actions';
        $this->addObjectTab();
        $this->content()->add($this->getForm());
    }

    public function bucketsAction()
    {
        $this->variant = 'buckets';
        $this->showList();
    }

    public function bucketAction()
    {
        $this->variant = 'buckets';
        $this->addObjectTab();
        $this->content()->add($this->getForm());
    }

    public function downtimesAction()
    {
        $this->variant = 'downtimes';
        $this->showList();
    }

    public function downtimeAction()
    {
        $this->variant = 'downtimes';
        $this->addObjectTab();
        /** @var DowntimeForm $form */
        $form = $this->getForm();
        $this->content()->add($form);
        if ($form->hasObject()) {
            $this->content()->add(new DowntimeScheduleTable($this->db(), $form->getObject()));
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

    protected function createForm(): UuidObjectForm
    {
        $store = $this->getStore();
        /** @var string|UuidObjectForm $formClass IDE hint */
        $formClass = $this->variant('form_class');
        $registryClass = $this->variant('registry');
        $form = new $formClass(new $registryClass, $store);
        $form->on($form::ON_SUCCESS, function (UuidObjectForm $form) {
            $this->redirectNow(Url::fromPath($this->variant('url'), [
                'uuid' => $form->getUuid()->toString()
            ]));
        });
        return $form;
    }

    protected function getForm(): UuidObjectForm
    {
        $form = $this->createForm();
        $objectType = $this->variant('singular');
        if ($this->params->has('uuid')) {
            $object = $this->getObject();
            if (isset($object->permissions)) {
                $object->permissions = JsonString::decode($object->permissions);
            }
            $this->flattenObjectSettings($object);
            $form->populate((array) $object);
            $this->addTitle("$objectType: %s", $object->label);
        } else {
            $this->addTitle($this->translate('Define a new %s'), $objectType);
        }
        $form->handleRequest($this->getServerRequest());
        if ($form->hasBeenDeleted()) {
            $this->redirectNow($this->variant('list_url'));
        }

        return $form;
    }

    protected function tabForList($name)
    {
        $tabs = $this->tabs();
        foreach ($this->variants as $key => $variant) {
            $tabs->add($key, [
                'label' => $variant['plural'],
                'url'   => $variant['list_url']
            ]);
        }
        $tabs->activate($name);
    }

    protected function addObjectTab()
    {
        $this->addSingleTab(sprintf($this->translate('%s Configuration'), $this->variant('singular')));
    }

    protected function getTableName()
    {
        return $this->variant('table');
    }

    protected function getStore()
    {
        if ($this->store === null) {
            $this->store = new ConfigStore($this->db());
        }

        return $this->store;
    }

    /**
     * @param UuidInterface $uuid
     * @param string $table
     * @return object
     */
    protected function loadObject(UuidInterface $uuid, $table)
    {
        return $this->getStore()->fetchObject($table, $uuid);
    }

    /**
     * @return object|null
     */
    protected function getObject()
    {
        if ($uuid = $this->params->get('uuid')) {
            return $this->loadObject(Uuid::fromString($uuid), $this->getTableName());
        }

        return null;
    }

    /**
     * @return object
     * @throws \Icinga\Exception\MissingParameterException
     */
    protected function requireObject()
    {
        return $this->loadObject(Uuid::fromString($this->params->getRequired('uuid')), $this->getTableName());
    }

    protected function flattenObjectSettings($object)
    {
        if (isset($object->settings)) {
            foreach ($object->settings as $key => $value) {
                $object->$key = $value;
            }
            unset($object->settings);
        }
    }
}
