<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Json\JsonDecodeException;
use gipfl\Json\JsonString;
use gipfl\Web\Widget\Hint;
use gipfl\ZfDbStore\DbStorableInterface;
use gipfl\ZfDbStore\NotFoundError;
use gipfl\ZfDbStore\ZfDbStore;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRule;
use Icinga\Module\Eventtracker\Engine\Input\KafkaInput;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Web\Dashboard\ConfigurationDashboard;
use Icinga\Module\Eventtracker\Web\Form\DowntimeForm;
use Icinga\Module\Eventtracker\Web\Form\UuidObjectForm;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;
use Icinga\Module\Eventtracker\Web\Table\ChannelRulesTable;
use Icinga\Module\Eventtracker\Web\Table\DowntimeScheduleTable;
use Icinga\Module\Eventtracker\Web\WebAction;
use Icinga\Module\Eventtracker\Web\WebActions;
use ipl\Html\Html;
use ipl\Html\Table;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ConfigurationController extends Controller
{
    /** @var ConfigStore */
    protected $store;

    /** @var WebActions */
    protected $actions;

    public function init()
    {
        $this->actions = new WebActions();
    }

    public function indexAction()
    {
        $this->setTitle($this->translate('Configuration'));
        $this->addSingleTab($this->translate('Configuration'));
        $this->content()->add(new ConfigurationDashboard($this->actions));
    }

    public function listenersAction()
    {
        $this->showList($this->actions->get('listeners'));
    }

    public function listenerAction()
    {
        $action = $this->actions->get('listeners');
        $this->addObjectTab($action);
        $this->content()->add($this->getForm($action));
        if ($object = $this->getObject($action)) {
            switch ($object->implementation) {
                case 'kafka':
                    $input = new KafkaInput(
                        Settings::fromSerialization($object->settings),
                        $this->getUuid(),
                        $object->label
                    );
                    $this->content()->add([
                        Hint::info([
                            Html::tag('strong', $this->translate('Command preview') . ': '),
                            Html::tag('pre', ['style' => 'background: none; line-height: 1.2em'], preg_replace(
                                '/( \'?-)/',
                                " \\\n   $1",
                                $input->prepareCommandString()
                            ))
                        ]),
                        Hint::warning($this->translate(
                            'Be careful when debugging: manually running this command AND the EventTracker Daemon would'
                            . ' result in both getting only parts of your Kafka events, as Kafka delivers them exactly'
                            . ' once'
                        ))
                    ]);
            }
        }
    }

    public function syncsAction()
    {
        $action = $this->actions->get('syncs');
        $this->tabForList($action);
        $this->addTitle($action->plural);
        $this->actions()->add($this->linkBack());
        $dummyTable = new Table();
        $dummyTable->addAttributes([
            'class' => ['common-table', 'table-row-selectable']
        ]);
        $dummyTable->getHeader()->add(Table::row([$action->plural], null, 'th'));
        $this->addCompactDashboard($dummyTable->add(
            Table::row([$this->translate(
                'This feature is not yet available, SCOM and IDO are still being synchronized the legacy way'
            )]),
        ));
    }

    public function apitokensAction()
    {
        $this->showList($this->actions->get('apitokens'));
    }

    public function apitokenAction()
    {
        $action = $this->actions->get('apitokens');
        $this->addObjectTab($action);
        $this->content()->add($this->getForm($action));
    }

    public function channelsAction()
    {
        $this->showList($this->actions->get('channels'));
    }

    public function channelAction()
    {
        $action = $this->actions->get('channels');
        if ($this->getUuid()) {
            $this->channelTabs()->activate('channel');
        } else {
            $this->addObjectTab($action);
        }
        $this->content()->add($this->getForm($action));
    }

    public function channelrulesAction()
    {
        $action = $this->actions->get('channels');
        $this->channelTabs()->activate('rules');
        $this->actions()->add(Link::create($this->translate('Edit'), 'TODO', [
            'what' => 'ever'
        ], [
            'class' => 'icon-edit'
        ]));
        $form = $this->getForm($action); // TODO: w/o form
        if ($rules = $form->getElementValue('rules')) {
            $this->showRules($this->requireUuid(), $rules);
        }
    }

    public function channelruleAction()
    {
        $action = $this->actions->get('channels');
        $modifierPosition = $this->params->get('modifier');
        if (null === $modifierPosition) {
            $label = $this->translate('Add new Rule');
        } else {
            $label = $this->translate('Rule');
        }
        $this->channelTabs()->add('rule', [
            'label' => $label,
            'url'   => 'eventtracker/configuration/channelrule',
            'urlParams' => $this->url()->getParams()->toArray(false),
        ])->activate('rule');
        $form = $this->getForm($action); // TODO: w/o form
    }

    protected function channelTabs()
    {
        $params = [
            'uuid' => $this->requireUuid()->toString()
        ];
        return $this->tabs()->add('channel', [
            'label' => $this->translate('Channel Configuration'),
            'url'   => 'eventtracker/configuration/channel',
            'urlParams' => $params,
        ])/*->add('rules', [
            'label' => $this->translate('Rules'),
            'url'   => 'eventtracker/configuration/channelrules',
            'urlParams' => $params,
        ])*/;
    }

    public function problemhandlingsAction()
    {
        $this->showList($this->actions->get('problemhandling'));
    }

    public function problemhandlingAction()
    {
        $action = $this->actions->get('problemhandling');
        $this->addObjectTab($action);
        $form = $this->getForm($action);
        if ($label = $this->params->get('label')) {
            $form->populate([
                'label' => $label
            ]);
        }
        $this->content()->add($form);
    }

    public function actionsAction()
    {
        $this->showList($this->actions->get('actions'));
    }

    public function actionAction()
    {
        $action = $this->actions->get('actions');
        $this->addObjectTab($action);
        $this->content()->add($this->getForm($action));
    }

    public function bucketsAction()
    {
        $this->showList($this->actions->get('buckets'));
    }

    public function bucketAction()
    {
        $action = $this->actions->get('buckets');
        $this->addObjectTab($action);
        $this->content()->add($this->getForm($action));
    }

    public function mapsAction()
    {
        $this->showList($this->actions->get('maps'));
    }

    public function mapAction()
    {
        $action = $this->actions->get('maps');
        $this->addObjectTab($action);
        $this->content()->add($this->getForm($action));
    }

    public function downtimesAction()
    {
        $this->showList($this->actions->get('downtimes'));
    }

    public function downtimeAction()
    {
        $action = $this->actions->get('downtimes');
        $this->addObjectTab($action);
        /** @var DowntimeForm $form */
        $form = $this->getForm($action);
        $this->content()->add($form);
        if ($form->hasObject()) {
            $this->content()->add(new DowntimeScheduleTable($this->db(), $form->getObject()));
        }
    }

    public function hostlistsAction()
    {
        $this->showList($this->actions->get('hostlists'));
    }

    public function hostlistAction()
    {
        $action = $this->actions->get('hostlists');
        $this->addObjectTab($action);
        $this->content()->add($this->getForm($action));
    }

    protected function showRules(UuidInterface $uuid, $rules)
    {
        try {
            $modifiers = ModifierChain::fromSerialization(JsonString::decode($rules));
        } catch (JsonDecodeException $e) {
            return;
        }
        $url = Url::fromPath('eventtracker/configuration/channelrule', [
            'uuid' => $uuid->toString()
        ]);
        $info = new ChannelRulesTable($modifiers, $url, $this->getServerRequest());
        $this->content()->add([
            Html::tag('h3', $this->translate('Configured Rules')),
            $info
        ]);
    }

    protected function showList(WebAction $action)
    {
        $this->addTitle(sprintf($this->translate('Configured %s'), $action->plural));
        $this->tabForList($action);
        $this->actions()->add([$this->linkBack(), $this->linkAdd($action)]);

        $class = $action->tableClass;
        /** @var BaseTable $table */
        $table = new $class($this->db(), $action);
        if ($table->count() > 0) {
            $this->addCompactDashboard($table);
        } else {
            $this->addCompactDashboard(Hint::info(sprintf(
                $this->translate('Please configure your first %s'),
                $action->singular
            )));
        }
    }

    protected function linkBack(): Link
    {
        return Link::create($this->translate('Back'), 'eventtracker/configuration', null, [
            'data-base-target' => '_main',
            'class' => 'icon-left-big',
        ]);
    }

    protected function linkAdd(WebAction $action): Link
    {
        return Link::create($this->translate('Add'), $action->url, null, [
            'data-base-target' => '_next',
            'class' => 'icon-plus',
        ]);
    }

    protected function addCompactDashboard($content)
    {
        $this->content()->add([
            Html::tag('div', [
                'class' => 'gipfl-compact-dashboard',
            ], new ConfigurationDashboard($this->actions)),
            Html::tag('div', [
                'class' => 'gipfl-content-next-to-compact-dashboard',
            ], $content)
        ]);
    }

    protected function createForm(WebAction $action): UuidObjectForm
    {
        $store = $this->getStore();
        /** @var string|UuidObjectForm $formClass IDE hint */
        $formClass = $action->formClass;
        if ($registryClass = $action->registry) {
            $form = new $formClass($store, new $registryClass);
        } else {
            $form = new $formClass($store);
        }
        $form->on($form::ON_SUCCESS, function (UuidObjectForm $form) use ($action) {
            if ($url = $this->getRelatedIssueUrl()) {
                $this->redirectNow($url);
            } else {
                $this->redirectNow(Url::fromPath($action->url, [
                    'uuid' => $form->getUuid()->toString()
                ]));
            }
        });
        return $form;
    }

    protected function getForm(WebAction $action): UuidObjectForm
    {
        $form = $this->createForm($action);
        $objectType = $action->singular;
        if ($this->getUuid()) {
            $object = $this->getObject($action);
            if ($object instanceof DbStorableInterface) {
                /** DowntimeForm only right now, need a form interface */
                $form->setObject($object);
                $label = $object->get('label');
            } else {
                if (isset($object->permissions)) {
                    $object->permissions = JsonString::decode($object->permissions);
                }
                $this->flattenObjectSettings($object);
                $form->populate((array) $object);
                $label = $object->label;
            }
            $this->addTitle("$objectType: %s", $label);
        } else {
            $this->addTitle($this->translate('Define a new %s'), $objectType);
        }
        $form->handleRequest($this->getServerRequest());
        if ($form->hasBeenDeleted()) {
            if ($url = $this->getRelatedIssueUrl()) {
                $this->redirectNow($url);
            } else {
                $this->redirectNow($action->listUrl);
            }
        }

        return $form;
    }


    protected function getRelatedIssueUrl(): ?Url
    {
        if ($this->params->get('issue_uuid')) {
            return Url::fromPath('eventtracker/issue', [
                'uuid' => $this->params->get('issue_uuid'),
            ]);
        }

        return null;
    }

    protected function tabForList(WebAction $action)
    {
        $tabs = $this->tabs();
        $tabs->add('index', [
            'label' => $this->translate('Configuration'),
            'url'   => 'eventtracker/configuration',
        ]);
        $tabs->add($action->name, [
            'label' => $action->plural,
            'url'   => $action->listUrl
        ]);
        $tabs->activate($action->name);
    }

    protected function addObjectTab(WebAction $action)
    {
        $this->addSingleTab(sprintf($this->translate('%s Configuration'), $action->singular));
    }

    protected function getStore(): ConfigStore
    {
        if ($this->store === null) {
            $this->store = new ConfigStore($this->db());
        }

        return $this->store;
    }

    protected function loadObject(UuidInterface $uuid, WebAction $action): object
    {
        if ($action->formClass === DowntimeForm::class) {
            $store = new ZfDbStore($this->db());
            $object = $store->load($uuid->getBytes(), DowntimeRule::class);
        } else {
            $object = $this->getStore()->fetchObject($action->table, $uuid);
        }

        if ($object) {
            return $object;
        }

        throw new NotFoundError(sprintf('UUID %s has not been found in %s', $uuid->toString(), $action->table));
    }

    protected function getObject(WebAction $action): ?object
    {
        if ($uuid = $this->getUuid()) {
            return $this->loadObject($uuid, $action);
        }

        return null;
    }

    protected function getUuid(): ?UuidInterface
    {
        $uuid = $this->params->get('uuid');
        if ($uuid !== null) {
            return Uuid::fromString($uuid);
        }

        return null;
    }

    protected function requireUuid(): UuidInterface
    {
        return Uuid::fromString($this->params->getRequired('uuid'));
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
