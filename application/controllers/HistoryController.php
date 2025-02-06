<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\Diff\HtmlRenderer\SideBySideDiff;
use gipfl\Diff\PhpDiff;
use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use gipfl\Json\JsonString;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\ConfigHistory;
use Icinga\Module\Eventtracker\Data\PlainObjectRenderer;
use Icinga\Module\Eventtracker\Web\Table\ActionHistoryTable;
use Icinga\Module\Eventtracker\Web\Table\ConfigurationHistoryTable;
use Icinga\Module\Eventtracker\Web\Table\IssueHistoryTable;
use Icinga\Module\Eventtracker\Web\Table\RawEventHistoryTable;
use Icinga\Module\Eventtracker\Web\WebActions;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;
use Icinga\Module\Eventtracker\Web\Widget\ConfigHistoryDetails;
use ipl\Html\Html;

class HistoryController extends Controller
{
    use IssuesFilterHelper;

    /** @var WebActions */
    protected $actions;

    public function init()
    {
        $this->actions = new WebActions();
    }

    public function actionsAction()
    {
        $this->addTitle('Action / Notification History');
        $this->historyTabs()->activate('actions');
        $this->setAutorefreshInterval(20);
        $db = $this->db();
        $table = new ActionHistoryTable($db, $this->url());
        if (! $this->url()->getParam('sort')) {
            $this->url()->setParam('sort', 'ts_done DESC');
        }
        $table->getQuery()->limit(50);
        (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
            ->appendTo($this->actions());
        $this->eventuallySendJson($table);
        $table->renderTo($this);
    }

    public function issuesAction()
    {
        $this->addTitle('Historic Issues');
        $this->historyTabs()->activate('issues');
        $this->setAutorefreshInterval(20);
        $db = $this->db();
        $table = new IssueHistoryTable($db, $this->url());
        $table->getQuery()->limit(50);
        (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
            ->appendTo($this->actions());
        $this->eventuallySendJson($table);
        $table->renderTo($this);
    }

    public function configurationAction()
    {
        $this->addTitle('Configuration History');
        $this->historyTabs()->activate('configuration');
        $this->setAutorefreshInterval(20);
        $db = $this->db();
        $table = new ConfigurationHistoryTable($db, $this->url());
        $table->getQuery()->limit(50);
        (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
            ->appendTo($this->actions());
        $this->eventuallySendJson($table);
        $table->renderTo($this);
    }

    protected static function renderForDiff($properties): string
    {
        if ($properties === null) {
            return '';
        }
        $object = JsonString::decode($properties);
        if ($object === null) {
            return '';
        }
        $ignoredProperties = ['config_uuid', 'next_calculated_uuid'];
        foreach ($ignoredProperties as $ignoredProperty) {
            unset($object->$ignoredProperty);
        }

        return PlainObjectRenderer::render($object);
    }

    public function configurationChangeAction()
    {
        $db = $this->db();
        $ts = $this->params->getRequired('ts');
        $change = $db->fetchRow($db->select()->from(ConfigHistory::TABLE_NAME)->where('ts_modification = ?', $ts));
        $webAction = $this->actions->getByTableName($change->object_type);
        $singular = $webAction->singular;
        $configDetails = new ConfigHistoryDetails($this->actions, $change);
        $this->addTitle(sprintf('%s %s: %s', $singular, $change->label, $change->action));
        $this->addSingleTab($this->translate('Configuration'));
        $diff = new SideBySideDiff(new PhpDiff(
            self::renderForDiff($change->properties_old),
            self::renderForDiff($change->properties_new)
        ));

        $t = new LocalTimeFormat();
        $d = new LocalDateFormat();

        $this->content()->add([
            Html::tag('p', $configDetails . ' ' . sprintf(
                $this->translate('on %s at %s'),
                $d->getFullDay(floor($ts / 1000)),
                $t->getShortTime(floor($ts / 1000))
            )),
            $diff
        ]);
    }

    public function rawAction()
    {
        $this->addTitle('Raw Event History');
        $this->historyTabs()->activate('raw');
        $this->setAutorefreshInterval(20);
        $db = $this->db();
        $table = new RawEventHistoryTable($db, $this->url());
        if (! $this->url()->getParam('sort')) {
            $this->url()->setParam('sort', 'ts_received DESC');
        }
        $table->getQuery()->limit(50);
        (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
            ->appendTo($this->actions());
        $this->eventuallySendJson($table);
        $table->renderTo($this);
    }

    protected function historyTabs()
    {
        return $this->tabs()->add('issues', [
            'label' => $this->translate('Issue History'),
            'url' => 'eventtracker/history/issues',
        ])->add('configuration', [
            'label' => $this->translate('Configuration'),
            'url' => 'eventtracker/history/configuration'
        ])->add('actions', [
            'label' => $this->translate('Actions'),
            'url' => 'eventtracker/history/actions'
        ])->add('raw', [
            'label' => $this->translate('Raw Events'),
            'url' => 'eventtracker/history/raw'
        ]);
    }
}
