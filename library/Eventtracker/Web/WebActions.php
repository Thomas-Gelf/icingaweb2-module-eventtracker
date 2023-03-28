<?php

namespace Icinga\Module\Eventtracker\Web;

use gipfl\Translation\TranslationHelper;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Eventtracker\Engine\Action\ActionRegistry;
use Icinga\Module\Eventtracker\Engine\Bucket\BucketRegistry;
use Icinga\Module\Eventtracker\Engine\Input\InputRegistry;
use Icinga\Module\Eventtracker\Web\Form\ActionConfigForm;
use Icinga\Module\Eventtracker\Web\Form\ApiTokenForm;
use Icinga\Module\Eventtracker\Web\Form\BucketConfigForm;
use Icinga\Module\Eventtracker\Web\Form\ChannelConfigForm;
use Icinga\Module\Eventtracker\Web\Form\DowntimeForm;
use Icinga\Module\Eventtracker\Web\Form\HostListForm;
use Icinga\Module\Eventtracker\Web\Form\InputConfigForm;
use Icinga\Module\Eventtracker\Web\Form\MapConfigForm;
use Icinga\Module\Eventtracker\Web\Table\DowntimeRulesTable;
use Icinga\Module\Eventtracker\Web\Table\WebActionTable;

class WebActions
{
    use TranslationHelper;

    protected $actions;

    protected $groups;

    public function __construct()
    {
        $this->init();
    }

    public function get(string $name): WebAction
    {
        if (! isset($this->actions[$name])) {
            throw new NotFoundError("'$name' not found");
        }

        return $this->actions[$name];
    }

    /**
     * @return array<string, WebAction[]>
     * @throws NotFoundError
     */
    public function getGroups(): array
    {
        $groups = [];
        foreach ($this->groups as $label => $keys) {
            if (! isset($groups[$label])) {
                $groups[$label] = [];
            }
            $current = &$groups[$label];
            foreach ($keys as $key) {
                $current[] = $this->get($key);
            }
        }

        return $groups;
    }

    public function init()
    {
        $this->groups = [
            $this->translate('Inputs, Event Sources')             => ['listeners', 'syncs', 'apitokens'],
            $this->translate('Event Processing, Action Handling') => ['channels', 'actions', 'downtimes'],
            $this->translate('Processing Utilities')              => ['buckets', 'maps', 'hostlists'],
        ];
        $this->actions = [
            'listeners' => WebAction::create([
                'name'        => 'listeners',
                'singular'    => $this->translate('Event Listener'),
                'plural'      => $this->translate('Event Listeners'),
                'description' => $this->translate('Passive Event Listeners, like Syslog, Kafka or our REST API'),
                'table'   => 'input',
                'listUrl' => 'eventtracker/configuration/listeners',
                'url'     => 'eventtracker/configuration/listener',
                'icon'    => 'angle-double-down',
                'tableClass' => WebActionTable::class,
                'formClass'  => InputConfigForm::class,
                'registry'   => InputRegistry::class,
            ]),
            'syncs' => WebAction::create([
                'name'        => 'syncs',
                'singular'    => $this->translate('Problem Sync'),
                'plural'      => $this->translate('Problem Syncs'),
                'description' => $this->translate(
                    'Synchronize Problems from external Monitoring tools, like Icinga or SCOM'
                ),
                'table'   => 'input',
                'listUrl' => 'eventtracker/configuration/syncs',
                'url'     => 'eventtracker/configuration/sync',
                'icon'    => 'reschedule',
                'tableClass' => WebActionTable::class,
                'formClass'  => InputConfigForm::class,
                'registry'   => InputRegistry::class,
            ]),
            'apitokens' => WebAction::create([
                'name'        => 'apitokens',
                'singular'    => $this->translate('API Token'),
                'plural'      => $this->translate('Api Tokens'),
                'description' => $this->translate(
                    'Define different API tokens for different senders, allowing'
                    . ' sender-based configuration'
                ),
                'table'   => 'api_token',
                'listUrl' => 'eventtracker/configuration/apitokens',
                'url'     => 'eventtracker/configuration/apitoken',
                'icon'    => 'lock-open-alt',
                'tableClass' => WebActionTable::class,
                'formClass'  => ApiTokenForm::class,
                'registry'   => InputRegistry::class,
            ]),
            'channels' => WebAction::create([
                'name'        => 'channels',
                'singular'    => $this->translate('Rule / Transformation'),
                'plural'      => $this->translate('Rules / Transformations'),
                'description' => $this->translate(
                    'Configure how to deal with incoming Events and Problems.'
                    . ' Tweak properties severity, reject or rate limit'
                ),
                'table'   => 'channel',
                'listUrl' => 'eventtracker/configuration/channels',
                'url'     => 'eventtracker/configuration/channel',
                'icon'    => 'beaker',
                'tableClass' => WebActionTable::class,
                'formClass'  => ChannelConfigForm::class,
                'registry'   => InputRegistry::class,
            ]),
            'actions' => WebAction::create([
                'name'        => 'actions',
                'singular' => $this->translate('Action'),
                'plural'   => $this->translate('Actions'),
                'description' => $this->translate(
                    'Send notifications, create tickets, call programs on new issues'
                    . ' and/or their recovery'
                ),
                'table'   => 'action',
                'listUrl' => 'eventtracker/configuration/actions',
                'url'     => 'eventtracker/configuration/action',
                'icon'    => 'bell',
                'tableClass' => WebActionTable::class,
                'formClass'  => ActionConfigForm::class,
                'registry'   => ActionRegistry::class,
            ]),
            'downtimes' => WebAction::create([
                'name'        => 'downtimes',
                'singular' => $this->translate('Downtime'),
                'plural'   => $this->translate('Downtimes'),
                'description' => $this->translate(
                    'Specify downtime rules, mostly for planned maintenance purposes'
                ),
                'table'   => 'downtime_rule',
                'listUrl' => 'eventtracker/configuration/downtimes',
                'url'     => 'eventtracker/configuration/downtime',
                'icon'    => 'plug',
                'tableClass' => DowntimeRulesTable::class,
                'formClass'  => DowntimeForm::class,
            ]),
            'buckets' => WebAction::create([
                'name'        => 'buckets',
                'singular'    => $this->translate('Bucket'),
                'plural'      => $this->translate('Buckets'),
                'description' => $this->translate(
                    'Buckets based on various rate-limiting implementations,'
                    . ' to be used in your Rule Sets'
                ),
                'table'   => 'bucket',
                'listUrl' => 'eventtracker/configuration/buckets',
                'url'     => 'eventtracker/configuration/bucket',
                'icon'    => 'filter',
                'tableClass' => WebActionTable::class,
                'formClass'  => BucketConfigForm::class,
                'registry'   => BucketRegistry::class,
            ]),
            'maps' => WebAction::create([
                'name'        => 'maps',
                'singular'    => $this->translate('Map lookup'),
                'plural'      => $this->translate('Map lookups'),
                'description' => $this->translate(
                    'Define key/value Maps, mostly for lookup purposes in your Rule Sets'
                ),
                'table'   => 'map',
                'listUrl' => 'eventtracker/configuration/maps',
                'url'     => 'eventtracker/configuration/map',
                'icon'    => 'flapping',
                'tableClass' => WebActionTable::class,
                'formClass'  => MapConfigForm::class,
            ]),
            'hostlists' => WebAction::create([
                'name'        => 'hostlists',
                'singular'    => $this->translate('Host list'),
                'plural'      => $this->translate('Host lists'),
                'description' => $this->translate(
                    'Hosts lists can be configured here, or synchronized from an external'
                    . ' Source'
                ),
                'table'   => 'host_list',
                'listUrl' => 'eventtracker/configuration/hostlists',
                'url'     => 'eventtracker/configuration/hostlist',
                'icon'    => 'th-list',
                'tableClass' => WebActionTable::class,
                'formClass'  => HostListForm::class,
            ]),
        ];
    }
}
