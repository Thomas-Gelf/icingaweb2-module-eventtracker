<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\Diff\HtmlRenderer\SideBySideDiff;
use gipfl\Diff\PhpDiff;
use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use gipfl\IcingaWeb2\Url;
use gipfl\Json\JsonString;
use gipfl\ZfDb\Select;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\ConfigHistory;
use Icinga\Module\Eventtracker\Data\PlainObjectRenderer;
use Icinga\Module\Eventtracker\Data\SerializationHelper;
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
    use RestApiMethods;

    protected $requiresAuthentication = false;

    /** @var WebActions */
    protected $actions;

    public function init()
    {
        if (! $this->getRequest()->isApiRequest()) {
            if (! $this->Auth()->isAuthenticated()) {
                $this->redirectToLogin(Url::fromRequest());
            }
        }
        $this->actions = new WebActions();
    }

    public function actionsAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->sendHistory('action_history', 'ts_done');
            return;
        }
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
        if ($this->getRequest()->isApiRequest()) {
            $this->sendHistory('issue_history', 'ts_first_event');
            return;
        }
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
        $this->notForApi();
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

    protected function sendHistory(string $table, string $tsColumn)
    {
        if ($this->getServerRequest()->getMethod() === 'GET') {
            $this->checkBearerToken('history/read');
            $this->runForApi(function () use ($table, $tsColumn) {
                $columns = $this->params->get('columns');
                if ($columns !== null) {
                    $columns = explode(",", $columns);
                }
                $from = $this->params->get('fromTimestampMs');
                $to = $this->params->get('toTimestampMs');
                $query = $this->prepareQueryString($table, $tsColumn, $columns, $from, $to);
                $this->sendQueryResultAsResponse($query);
            });
        }

        $this->sendJsonError('Invalid method for this endpoint', 405);
    }

    protected function sendQueryResultAsResponse(Select $query)
    {
        $firstRow = true;
        $statement = $this->db()->query($query);
        while ($row = $statement->fetch()) {
            unset($row->activities);
            if ($firstRow) {
                $this->sendJsonResponseHeaders();
                echo '{ "objects": [';
                $firstRow = false;
            } else {
                echo ", ";
            }
            echo JsonString::encode(SerializationHelper::serializeProperties((array) $row), JSON_PRETTY_PRINT);
        }
        if ($firstRow) {
            $this->sendJsonResponseHeaders();
            echo '{ "objects": []}' . "\n";
        } else {
            echo "]}\n";
        }
        $this->getViewRenderer()->disable();
        exit;
    }
    protected function prepareQueryString(
        string $table,
        string $tsColumn,
        array  $columns = null,
        ?int   $from = null,
        ?int   $to = null
    ): Select {
        $test = $this->db()->select();
        $test = $this->db()->select()->from($table, $columns);
        $query = $this->db()->select()->from($table, $columns ?? '*');
        if ($from) {
            $query->where("$tsColumn >= ?", $from);
        }
        if ($to) {
            $query->where("$tsColumn <= ?", $to);
        }

        return $query;
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
        $this->notForApi();
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
