<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\Icon;
use gipfl\Json\JsonString;
use gipfl\Web\Widget\Hint;
use Icinga\Date\DateFormatter;
use Icinga\Module\Eventtracker\Format;
use Icinga\Module\Eventtracker\Web\Form\LogLevelForm;
use Icinga\Module\Eventtracker\Web\Form\RestartDaemonForm;
use Icinga\Module\Eventtracker\WebUtil;
use Icinga\Web\Notification;
use ipl\Html\Html;
use ipl\Html\Table;

class DaemonController extends Controller
{
    use AsyncControllerHelper;

    public function indexAction()
    {
        $this->assertPermission('eventtracker/admin');
        $this->setAutorefreshInterval(30);
        $this->addTitle($this->translate('EventTracker Daemon Status'));
        $this->addSingleTab($this->translate('Daemon Status'));
        $this->content()->add([
            Html::tag('h3', $this->translate('Damon Processes')),
            $this->prepareDaemonInfo(),
            Html::tag('h3', $this->translate('Damon Log Output')),
            $this->prepareLogSettings(),
            $this->prepareLogWindow()
        ]);
    }

    protected function prepareLogSettings()
    {
        $logLevelForm = new LogLevelForm($this->remoteClient(), $this->loop());
        $logLevelForm->on($logLevelForm::ON_SUCCESS, function () {
            $this->redirectNow($this->url());
        });
        $logLevelForm->handleRequest($this->getServerRequest());
        if ($logLevelForm->talkedToSocket()) {
            return [$this->translate('Log level') . ': ', $logLevelForm];
        }

        return null;
    }

    protected function prepareDaemonInfo()
    {
        $db = $this->db();
        $daemon = $db->fetchRow(
            $db->select()
                ->from('daemon_info')
                ->order('ts_last_update DESC')
                ->limit(1)
        );

        if ($daemon) {
            if ($daemon->ts_last_update / 1000 < time() - 10) {
                return Hint::error(Html::sprintf(
                    "Daemon keep-alive is outdated in our database, last refresh was %s",
                    WebUtil::timeAgo($daemon->ts_last_update / 1000)
                ));
            } else {
                $restartForm = new RestartDaemonForm($this->remoteClient(), $this->loop());
                $restartForm->on($restartForm::ON_SUCCESS, function () {
                    Notification::success('Daemon has been asked to restart');
                    $this->redirectNow($this->url());
                });
                $restartForm->handleRequest($this->getServerRequest());

                return [$restartForm, $this->prepareProcessTable(JsonString::decode($daemon->process_info))];
            }
        } else {
            return Hint::error($this->translate('Daemon is either not running or not connected to the Database'));
        }
    }

    protected function prepareProcessTable($processes)
    {
        $table = new Table();
        foreach ($processes as $pid => $process) {
            $table->add($table::row([
                [
                    Icon::create($process->running ? 'ok' : 'warning-empty'),
                    ' ',
                    $pid
                ],
                $process->command ?? '-',
                Format::bytes($process->memory->rss)
            ]));
        }

        return $table;
    }

    protected function prepareLogWindow()
    {
        $db = $this->db();
        $lineCount = 1000;
        $logLines = [];
        /*
        $logLines = $db->fetchAll($db->select()
            ->from('vspheredb_daemonlog')
            ->order('ts_create DESC')
            ->limit($lineCount));
        */
        $log = Html::tag('pre', ['class' => 'logOutput']);
        $logWindow = Html::tag('div', ['class' => 'logWindow'], $log);
        foreach ($logLines as $line) {
            $ts = $line->ts_create / 1000;
            if ($ts + 3600 * 16 < time()) {
                $tsFormatted = DateFormatter::formatDateTime($ts);
            } else {
                $tsFormatted = DateFormatter::formatTime($ts);
            }
            $log->add(Html::tag('div', [
                'class' => $line->level
            ], "$tsFormatted: " . $line->message));
        }

        return $logWindow;
    }
}
