<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Exception;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Json\JsonDecodeException;
use gipfl\Json\JsonString;
use gipfl\Web\InlineForm;
use gipfl\Web\Widget\Hint;
use gipfl\ZfDbStore\DbStorableInterface;
use gipfl\ZfDbStore\NotFoundError;
use gipfl\ZfDbStore\ZfDbStore;
use Icinga\Chart\Inline\Inline;
use Icinga\Module\Eventtracker\Data\PlainObjectRenderer;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRule;
use Icinga\Module\Eventtracker\Engine\Input\KafkaInput;
use Icinga\Module\Eventtracker\Modifier\Modifier;
use Icinga\Module\Eventtracker\Modifier\ModifierRuleStore;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\RuleStore;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Syslog\SyslogParser;
use Icinga\Module\Eventtracker\Web\Dashboard\ConfigurationDashboard;
use Icinga\Module\Eventtracker\Web\Form\ChannelConfigForm;
use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use Icinga\Module\Eventtracker\Web\Form\DowntimeForm;
use Icinga\Module\Eventtracker\Web\Form\SimulateRuleForm;
use Icinga\Module\Eventtracker\Web\Form\UuidObjectForm;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;
use Icinga\Module\Eventtracker\Web\Table\ChannelRulesTable;
use Icinga\Module\Eventtracker\Web\Table\DowntimeRulesTable;
use Icinga\Module\Eventtracker\Web\Table\DowntimeScheduleTable;
use Icinga\Module\Eventtracker\Web\Table\HostListMemberTable;
use Icinga\Module\Eventtracker\Web\WebAction;
use Icinga\Module\Eventtracker\Web\WebActions;
use Icinga\Web\Notification;
use Icinga\Web\Session\SessionNamespace;
use ipl\Html\FormElement\SubmitButtonElement;
use ipl\Html\FormElement\SubmitElement;
use ipl\Html\Html;
use ipl\Html\Table;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function _PHPStan_90b10482a\React\Async\waterfall;
use function ipl\Stdlib\get_php_type;

class ConfigurationController extends Controller
{
    use AsyncControllerHelper;
    use RestApiMethods;
    /** @var ConfigStore */
    protected $store;
    protected $requiresAuthentication = false;
    /** @var WebActions */
    protected $actions;

    public function init()
    {
        if (! $this->getRequest()->isApiRequest()) {
            if (! $this->Auth()->isAuthenticated()) {
                $this->redirectToLogin(Url::fromRequest());
            }
            $this->assertPermission('eventtracker/admin');
        }
        $this->actions = new WebActions();
    }

    public function indexAction()
    {
        $this->notForApi();
        $this->setTitle($this->translate('Configuration'));
        $this->addSingleTab($this->translate('Configuration'));
        $this->content()->add(new ConfigurationDashboard($this->actions));
    }

    public function listenersAction()
    {
        $this->notForApi();
        $this->showList($this->actions->get('listeners'));
    }

    public function listenerAction()
    {
        $this->notForApi();
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
        $this->notForApi();
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
            )])
        ));
    }

    public function apitokensAction()
    {
        $this->notForApi();
        $this->showList($this->actions->get('apitokens'));
    }

    public function apitokenAction()
    {
        $this->notForApi();
        $action = $this->actions->get('apitokens');
        $this->addObjectTab($action);
        $this->content()->add($this->getForm($action));
    }

    public function channelsAction()
    {
        $this->notForApi();
        $this->showList($this->actions->get('channels'));
    }

    public function channelAction()
    {
        $this->notForApi();
        $action = $this->actions->get('channels');
        if ($this->getUuid()) {
            $this->channelTabs()->activate('channel');
        } else {
            $this->addObjectTab($action);
        }
        $this->content()->add($this->getForm($action, function () {
            if ($uuid = $this->getUuid()) {
                $ns = $this->Window()->getSessionNamespace('eventtracker');
                $sessionKey = 'channelrules/' . $uuid->toString();
                $ns->delete($sessionKey);
            }
        }));
    }

    public function channelrulesAction()
    {
        $this->notForApi();
//        $this->add
        $action = $this->actions->get('channels');
        $this->channelTabs()->activate('rules');
        $this->actions()->add(Link::create($this->translate('Add Modifier'), Url::fromPath(
            'eventtracker/configuration/channelRules/',
            ['uuid' => $this->requireUuid(),
            'action' => 'add']
        )));

//        $this->actions()->add(Link::create($this->translate('Edit'), 'TODO', [
//            'what' => 'ever'
//        ], [
//            'class' => 'icon-edit'
//        ]));
        $uuid = $this->requireUuid();
        $ns = $this->Window()->getSessionNamespace('eventtracker');
        $sessionKey = 'simulationObject/'. $uuid->toString();

        $form = $this->getForm($action); // TODO: w/o form
        $modifierRuleStore = new ModifierRuleStore($ns, $uuid, $form);
        $rules = $modifierRuleStore->getStoredRules();
        $rules = $modifierRuleStore->getRules();
//        $form->addElement('submit', 'submit', ['label' => $this->translate('Save32')]);
//        $this->addContent($form);
        if ($this->params->get('action') === 'add') {
            $ruleForm = new ChannelRuleForm();
            $ruleForm->handleRequest($this->getServerRequest());

            if ($ruleForm->hasBeenSubmitted()) {
                $rules = $modifierRuleStore->getRules();
                $rules->addModifier($ruleForm->getModifier(), $ruleForm->getPropertyName());
                $modifierRuleStore->setModifierRules($rules);
                $this->redirectNow($this->url()->getUrlWithout('action'));
            }
            $this->content()->add($ruleForm);
        }
        if ($ns->get($sessionKey) !== null) {
            $this->actions()->add(Link::create($this->translate('Stop Simulation'), Url::fromPath(
                'eventtracker/configuration/channelRules/',
                ['uuid' => $this->requireUuid(),
                    'action' => 'stop_simulation']
            )));
        }
        else if ($this->params->get('action') === 'load_simulation') {
            $simulationForm = new SimulateRuleForm($ns, $sessionKey);
            $simulationForm->handleRequest($this->getServerRequest());
            if ($simulationForm->hasBeenSubmitted()) {
                $this->redirectNow($this->url()->getUrlWithout('action'));
            }
            $this->content()->add($simulationForm);
        } else {
            $this->actions()->add(Link::create($this->translate('Load Simulation'), Url::fromPath(
                'eventtracker/configuration/channelRules/',
                ['uuid' => $this->requireUuid(),
                    'action' => 'load_simulation']
            )));
        }
        if ($this->params->get('action') === 'stop_simulation') {
            $ns->delete($sessionKey);
            $this->redirectNow($this->url()->getUrlWithout('action'));
        }
        $this->showRules($modifierRuleStore, $ns, $uuid, $form);
    }

    public function channelruleAction()
    {
        $this->notForApi();
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
        $this->notForApi();
        $params = [
            'uuid' => $this->requireUuid()->toString()
        ];
        return $this->tabs()->add('channel', [
            'label' => $this->translate('Channel Configuration'),
            'url'   => 'eventtracker/configuration/channel',
            'urlParams' => $params,
        ])->add('rules', [
            'label' => $this->translate('Rules'),
            'url'   => 'eventtracker/configuration/channelrules',
            'urlParams' => $params,
        ]);
    }


    public function problemhandlingsAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            switch ($this->getServerRequest()->getMethod()) {
                case 'GET':
                    $this->checkBearerToken('problemhandling/read');
                    $this->runForApi(function () {
                        $this->getProblemHandlings();
                    });
                    break;
                case 'POST':
                    $this->checkBearerToken('problemhandling/write');
                    $this->runForApi(function () {
                    });
                    break;
            }
        } else {
            $this->showList($this->actions->get('problemhandling'));
        }
    }

    protected function getProblemHandlings()
    {
        $action = $this->actions->get('problemhandling');

        $table = $this->prepareTableForList($action);
        $this->sendJsonResponse(self::cleanRows($table->db()->fetchAll($table->getQuery())));
    }

    public function problemhandlingAction()
    {
        $this->notForApi();
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
        $this->notForApi();
        $this->showList($this->actions->get('actions'));
    }

    public function actionAction()
    {
        $this->notForApi();
        $action = $this->actions->get('actions');
        $this->addObjectTab($action);
        $this->content()->add($this->getForm($action));
    }

    public function bucketsAction()
    {
        $this->notForApi();
        $this->showList($this->actions->get('buckets'));
    }

    public function bucketAction()
    {
        $this->notForApi();
        $action = $this->actions->get('buckets');
        $this->addObjectTab($action);
        $this->content()->add($this->getForm($action));
    }

    public function mapsAction()
    {
        $this->notForApi();
        $this->showList($this->actions->get('maps'));
    }

    public function mapAction()
    {
        $this->notForApi();
        $action = $this->actions->get('maps');
        $this->addObjectTab($action);
        $this->content()->add($this->getForm($action));
    }

    public function downtimesAction()
    {
        $this->notForApi();
        $this->showList($this->actions->get('downtimes'));
    }

    public function downtimeAction()
    {
        $this->notForApi();
        $action = $this->actions->get('downtimes');
        $this->addObjectTab($action);
        /** @var DowntimeForm $form */
        $form = $this->getForm($action, function () {
            try {
                $this->syncRpcCall('eventtracker.reloadDowntimeRules');
            } catch (Exception $e) {
                Notification::warning(sprintf(
                    $this->translate('Failed to notify Eventtracker daemon: %s'),
                    $e->getMessage()
                ));
            }
        });
        $this->content()->add($form);
        if ($form->hasObject()) {
            $table = new DowntimeScheduleTable($form->getObject());
            if (count($table) === 0) {
                $this->content()->add(
                    Hint::info($this->translate('Currently, no iteration has been scheduled for this downtime'))
                );
            } else {
                $this->content()->add($table);
            }
        }
    }

    protected function refreshDaemonHostLists(): bool
    {
        try {
            $this->syncRpcCall('eventtracker.reloadHostLists');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function hostlistsAction()
    {

        if ($this->getRequest()->isApiRequest()) {
            switch ($this->getServerRequest()->getMethod()) {
                case 'GET':
                    $this->checkBearerToken('host_list/read');
                    $this->runForApi(function () {
                        $this->getHostLists();
                    });
                    break;
                case 'POST':
                    $this->checkBearerToken('host_list/write');
                    $this->runForApi(function () {
                        $this->postHostLists();
                    });
                    break;
            }
        } else {
            $action = $this->actions->get('hostlists');
            $this->showList($action);
        }
    }
    protected function getHostLists()
    {
        $action = $this->actions->get('hostlists');

        $table = $this->prepareTableForList($action);
        $this->sendJsonResponse(self::cleanRows($table->db()->fetchAll($table->getQuery())));
    }
    protected function postHostLists()
    {
        $body = $this->requireJsonBody();
        $cnt = 0;
        $this->runAsTransaction(function () use ($body, &$cnt) {
            foreach ($body as $hostlist) {
                $cnt++;
                $hostlist->uuid = Uuid::uuid4()->getBytes();
                $this->db()->insert('host_list', (array) $hostlist);
            }
        });
        $this->sendJsonSuccess(['message' => sprintf('added  %s hostlists', $cnt)]);
    }

    public function hostlistAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            switch ($this->getServerRequest()->getMethod()) {
                case 'GET':
                    $this->checkBearerToken('host_list/read');
                    $this->runForApi(function () {
                        $this->getHostList();
                    });
                    break;
                case 'POST':
                    $this->checkBearerToken('host_list/write');
                    $this->runForApi(function () {
                        $this->postHostList();
                    });
                    break;
                case 'PUT':
                    $this->checkBearerToken('host_list/write');
                    break;
                case 'DELETE':
                    $this->checkBearerToken('host_list/write');
                    break;
            }
        } else {
            $action = $this->actions->get('hostlists');
            $this->addObjectTab($action);
            $this->content()->add($this->getForm($action));
            if ($uuid = $this->getUuid()) {
                $this->content()->add(new HostListMemberTable($this->db(), $uuid));
            }
        }
    }

    protected function getHostList()
    {
        $uuid = Uuid::fromString($this->params->getRequired('listUuid'))->getBytes();
        $this->sendJsonResponse(self::cleanRows(array($this->db()->fetchRow(
            $this->db()
                ->select()->from('host_list', ['label'])
                ->where('uuid = ?', $uuid)
        ))));
    }

    protected function postHostList()
    {
        $body = $this->requireJsonBody();
        $this->db()->insert('host_list', [
            'uuid'  => Uuid::uuid4()->getBytes(),
            'label' => $body->label,
        ]);
        $this->refreshDaemonHostLists();
        $this->sendJsonSuccess([
            'message' => sprintf('added hostlist %s', $body->label)
        ]);
    }

    public function hostlistMemberAction()
    {
        $this->showApiOnly();
        switch ($this->getServerRequest()->getMethod()) {
            case 'GET':
                $this->checkBearerToken('host_list/read');
                $this->runForApi(function () {
                    $this->getHostListMember();
                });
                break;
            case 'POST':
                $this->checkBearerToken('host_list/write');
                $this->runForApi(function () {
                    $this->postHostlistMember();
                });
                break;
            case 'DELETE':
                $this->checkBearerToken('host_list/write');
                $this->runForApi(function () {
                    $this->deleteHostListMember();
                });
                break;
        }
    }

    protected function getHostListMember()
    {
        $uuid = Uuid::fromString($this->params->getRequired('listUuid'))->getBytes();
        $this->sendJsonResponse($this->db()->fetchCol(
            $this->db()
                ->select()->from('host_list_member', ['hostname'])
                ->where('list_uuid = ?', $uuid)
        ));
    }

    protected function postHostListMember()
    {
        $body = $this->requireJsonBody();
        $uuid = Uuid::fromString($this->params->getRequired('listUuid'))->getBytes();
        $hostListMember = [
            'list_uuid' => $uuid,
            'hostname ' => $body->hostname,
        ];
        $this->db()->insert('host_list_member', $hostListMember);
        $this->refreshDaemonHostLists();
        $hostlist = $this->db()->fetchOne(
            $this->db()->select()
                ->from('host_list', ['label'])
                ->where('uuid = ?', $uuid)
        );
        $this->sendJsonSuccess(['message' => sprintf('added host %s to hostlist %s', $body->hostname, $hostlist)]);
    }

    protected function deleteHostListMember()
    {
        $listUuid = Uuid::fromString($this->params->getRequired('listUuid'));
        $hostname = $this->params->getRequired('hostname');
        $db = $this->db();

        if ($db->delete(
            'host_list_member',
            $db->quoteInto('list_uuid = ?', $listUuid->getBytes())
            . $db->quoteInto(' AND hostname = ?', $hostname)
        ) > 0) {
            $this->refreshDaemonHostLists();
            $this->sendJsonSuccess(['message' => sprintf('deleted host list member %s', $hostname)]);
        } else {
            $this->sendJsonSuccess(['message' => 'Nothing has been deleted']);
        }
    }

    public function hostlistMembersAction()
    {
        $this->showApiOnly();
        switch ($this->getServerRequest()->getMethod()) {
            case "GET":
                $this->checkBearerToken('host_list/read');
                $this->runForApi(function () {
                    $this->getHostListMembers();
                });
                break;
            case "POST":
                $this->checkBearerToken('host_list/write');
                $this->runForApi(function () {
                    $this->postHostListMembers();
                });
                break;
            case 'PUT':
                $this->checkBearerToken('host_list/write');
                $this->runForApi(function () {
                    $this->putHostListMembers();
                });
                break;
        }
    }

    protected function getHostListMembers()
    {
        $action = $this->actions->get('hostlistMembers');

        $uuid = Uuid::fromString($this->params->getRequired('listUuid'));

        $this->sendJsonResponse(self::cleanRows($this->db()->fetchAll(
            $this->db()->select()->from($action->table, ['hostname'])->where('list_uuid = ?', $uuid->getBytes())
        )));
    }
    protected function postHostListMembers()
    {
        $uuid = Uuid::fromString($this->params->getRequired('listUuid'))->getBytes();
        $body = $this->requireJsonBody();
        $cnt = 0;
        $db = $this->db();
        $currentMembers = $db->fetchCol(
            $db->select()->from('host_list_member', 'hostname')->where('list_uuid = ?', $uuid)
        );
        $this->runAsTransaction(function () use ($body, &$cnt, $uuid, $currentMembers) {
            foreach ($body as $member) {
                if (in_array($member->hostname, $currentMembers)) {
                    continue;
                }
                $cnt++;
                $member->list_uuid = $uuid;
                $this->db()->insert('host_list_member', (array) $member);
            }
        });
        $this->sendJsonSuccess(['message' => sprintf('added  %s hosts', $cnt)]);
    }

    protected function putHostListMembers()
    {
        $uuid = Uuid::fromString($this->params->getRequired('listUuid'))->getBytes();
        $body = $this->requireJsonBody();
        $cnt = 0;
        $db = $this->db();
        $currentMembers = $db->fetchCol(
            $db->select()->from('host_list_member', 'hostname')->where('list_uuid = ?', $uuid)
        );
        $membersRequested = [];
        $this->runAsTransaction(function () use ($db, $body, &$cnt, $uuid, $currentMembers, $membersRequested) {
            foreach ($body as $member) {
                $membersRequested[] = $member->hostname;
                if (in_array($member->hostname, $currentMembers)) {
                    continue;
                }
                $cnt++;
                $member->list_uuid = $uuid;
                $this->db()->insert('host_list_member', (array) $member);
            }
            foreach ($currentMembers as $member) {
                if (! in_array($member, $membersRequested)) {
                    $db->delete('host_list_member', $db->quoteInto('list_uuid = ?', $uuid)
                        . $db->quoteInto(' AND hostname = ?', $member));
                }
            }
        });
        $this->refreshDaemonHostLists();

        $this->sendJsonSuccess(['message' => sprintf('updated  %s hosts', $cnt)], 201);
    }

    protected function showRules(ModifierRuleStore $modifierRuleStore, SessionNamespace $ns, UuidInterface $uuid, UuidObjectForm $form)
    {
        $sessionKey = 'channelrules/' . $uuid->toString();
        $form->addElement('submit', 'add_modifier', []);
        
        if ($modifierRuleStore->getSessionRules() !== null) {
            if ($modifierRuleStore->hasBeenModified()) {
                $newForm = new InlineForm();
                $newForm->handleRequest($this->getServerRequest());
                $newForm->addElement('submit', 'submit', ['label' => $this->translate('Save')]);
                $message = Hint::warning(
                    'The order has been modified please safe if you want to preserver the new order'
                );
//                $form->addHtml(Hint::warning(
//                    'The order has been modified please safe if you want to preserver the new order'
//                ));
                if ($newForm->hasBeenSubmitted()) {
                    $form->populate(['rules' => JsonString::encode($modifierRuleStore->getRules())]);
                    $form->storeObject();
                    $modifierRuleStore->deleteSessionRules();
                    $this->redirectNow($this->url());
                }
                $this->content()->add([$message, $newForm]);
            }
            // $modifierRuleStore->setModifierRules($modifierRuleStore->getSessionRules());
        }
        if ($modifierRuleStore->getRules() === null) {
            var_dump("foobar");
            return;
        }
        try {
            //$modifiers = ModifierChain::fromSerialization($modifierRuleStore->getRules());
            $modifiers = $modifierRuleStore->getRules();
        } catch (JsonDecodeException $e) {
            $this->content()->add(Hint::error(sprintf(
                $this->translate('Configured rules are invalid: %s'),
                $e->getMessage()
            )));
            return;
        }
//        $ns->set('channelrules/' . $uuid->toString(), 'test');
//        $ns->get()
        $url = Url::fromPath('eventtracker/configuration/channelrule', [
            'uuid' => $uuid->toString()
        ]);
        try {
            if ($sampleObject = $ns->get('simulationObject/'. $uuid->toString())) {
                $this->content()->add([
                    Html::tag('h3', "Original Event"),
                    Html::tag('pre', [
                        'class' => 'plain-object'
                    ], PlainObjectRenderer::render($sampleObject)),
                ]);
            }
            $info = new ChannelRulesTable(
                $modifiers,
                $url,
                $this->getServerRequest(),
                $sampleObject,
                $modifierRuleStore
            );
            if ($info->hasModifications()) {
                $this->redirectNow($this->url());
            }
        } catch (\Throwable $e) {
            $info = [
                 Hint::error($e),
                 Html::tag('pre', $modifierRuleStore->getRules()),
                 Html::tag('pre', $modifierRuleStore->getRules(), JSON_PRETTY_PRINT),
            ];
        }


        $this->content()->add([
            Html::tag('h3', $this->translate('Configured Rules')),
            $info
        ]);
    }

    protected function getSampleObject()
    {
        return SyslogParser::parseLine(
            'Jan 11 13:12:54 goj oem_syslog[2837832]: timestamp=2025-01-11T12:12:54.560Z'
            . ' hostname=kri.example.com component=kri.example.com id=2837644 state=nok severity=2 oem_clear=false'
            . ' oem_host_name=kri.example.com oem_incident_ack_by_owner=no url=https://ip.gelf.net oem_incident_id=4996'
            . ' oem_incident_status=new'
            . ' oem_issue_type=incident oem_target_name=kri.example.com oem_target_type=host msg=Alert; Value=7;'
            . ' String <Returncode:> with values <> 0 found in /var/log/dbms/load_dbclone_for_oracle_mssql.log!'
            . ' OEMIncidentID: 4996'
        );
        return SyslogParser::parseLine(
            'Jan 11 13:12:54 goj oem_syslog[2837832]: timestamp=2025-01-11T12:12:54.560Z'
            . ' hostname=kri.example.com component=kri.example.com id=2837644 state=nok severity=2 oem_clear=false'
            . ' oem_host_name=kri.example.com oem_incident_ack_by_owner=no oem_incident_id=4996 oem_incident_status=new'
            . ' oem_issue_type=incident oem_target_name=kri.example.com oem_target_type=host msg=Alert; Value=7;'
            . ' String <Returncode:> with values <> 0 found in /var/log/dbms/dbclone_for_oracle_mssql.log!'
            . ' OEMIncidentID: 4996'
        );
        return JsonString::decode(
            '{' . "\n"
            . '    "host_name": "goj",' . "\n"
            . '    "object_name": "oem_syslog",' . "\n"
            . '    "object_class": "user",' . "\n"
            . '    "severity": "critical",' . "\n"
            . '    "priority": null,' . "\n"
            . '    "message": "timestamp=2025-02-02T13:24:30.276Z hostname=atb.example.com'
            . ' component=DBMS03_SITE1.EXAMPLE id=125690 state=nok severity=1 oem_clear=false'
            . ' oem_host_name=atb.example.com oem_incident_ack_by_owner=no oem_incident_id=144826'
            . ' oem_incident_status=new oem_issue_type=incident oem_target_name=dbms03_site1.example'
            . ' oem_target_type=oracle_pdb msg=The pluggable database DBMS03_SITE1.EXAMPLE is down.'
            . ' OEMIncidentID: 144826",' . "\n"
            . '    "attributes": {' . "\n"
            . '        "syslog_sender_pid": 125795' . "\n"
            . '    }' . "\n"
            . '}' . "\n"
        );
        return null;
    }

    protected function showList(WebAction $action)
    {
        $this->setAutorefreshInterval(15);
        $this->addTitle(sprintf($this->translate('Configured %s'), $action->plural));
        $this->tabForList($action);
        $this->actions()->add([$this->linkBack(), $this->linkAdd($action)]);

        $table = $this->prepareTableForList($action);
        if ($table instanceof DowntimeRulesTable) {
            try {
                $slots = $this->syncRpcCall('eventtracker.getActiveTimeSlots');
            } catch (Exception $e) {
                $this->content()->add(Hint::warning(
                    $this->translate('Got no active time slot info, is the daemon running?')
                ));
                $slots = [];
            }
            $table->setActiveTimeSlots((array) $slots);
        }
        if ($table->count() > 0) {
            $this->addCompactDashboard($table);
        } else {
            $this->addCompactDashboard(Hint::info(sprintf(
                $this->translate('Please configure your first %s'),
                $action->singular
            )));
        }
    }

    protected function prepareTableForList(WebAction $action)
    {
        $class = $action->tableClass;
        /** @var BaseTable $table */
        return new $class($this->db(), $action);
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

    protected function createForm(WebAction $action, ?callable $onSuccess): UuidObjectForm
    {
        $store = $this->getStore();
        /** @var string|UuidObjectForm $formClass IDE hint */
        $formClass = $action->formClass;
        if ($registryClass = $action->registry) {
            $form = new $formClass($store, new $registryClass);
        } else {
            $form = new $formClass($store);
        }
        /** @var UuidObjectForm */
        if ($onSuccess) {
            $form->on($form::ON_SUCCESS, $onSuccess);
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

    protected function getForm(WebAction $action, ?callable $onSuccess = null): UuidObjectForm
    {
        $form = $this->createForm($action, $onSuccess);
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
                $this->redirectNow($action->listUrl . '#!__CLOSE__');
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

    protected function runAsTransaction(callable $callback)
    {
        $db = $this->db();
        $this->db()->beginTransaction();
        try {
            $callback();
            $db->commit();
        } catch (\Throwable $e) {
            try {
                $db->rollBack();
            } catch (Exception $e) {
                // ignore
            }

            throw $e;
        }
    }
    protected static function cleanRows($rows)
    {
        foreach ($rows as &$row) {
            $row = self::cleanRow($row);
        }

        return $rows;
    }

    protected static function cleanRow($row)
    {
        $row = (array)$row;
        foreach ($row as $k => &$v) {
            if ($v === null) {
                continue;
            }
            if (strpos($k, 'uuid') !== false) {
                $v = Uuid::fromBytes($v)->toString();
            }
        }

        return (object) $row;
    }
    protected function requireJsonBody()
    {
        $body = (string) $this->getServerRequest()->getBody();
        if (strlen($body) === 0) {
            $this->sendJsonError('JSON body is required, 400');
        }

        return JsonString::decode($body);
    }

    protected function sendJsonSuccess(array $properties, $code = 200)
    {
        $this->sendJsonResponse([
                'success' => 'true',
            ] + $properties, $code);
    }
}
