<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Exception;
use gipfl\Cli\Process;
use gipfl\IcingaCliDaemon\DbResourceConfigWatch;
use gipfl\SystemD\NotifySystemD;
use Icinga\Module\Eventtracker\Configuration;
use Icinga\Module\Eventtracker\Daemon\RpcNamespace\RpcNamespaceProcess;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRunner;
use Icinga\Module\Eventtracker\Engine\Downtime\GeneratedDowntimeGenerator;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory as Loop;
use React\EventLoop\LoopInterface;
use Ramsey\Uuid\Uuid;
use React\Stream\Util as StreamUtil;
use function React\Promise\all;
use function React\Promise\reject;

class BackgroundDaemon implements EventEmitterInterface
{
    use EventEmitterTrait;

    /** @var LoopInterface */
    private $loop;

    /** @var NotifySystemD|boolean */
    protected $systemd;

    /** @var JobRunner */
    protected $jobRunner;

    /** @var InputAndChannelRunner */
    protected $channelRunner;

    /** @var string|null */
    protected $dbResourceName;

    /** @var DaemonDb */
    protected $daemonDb;

    /** @var DaemonProcessState */
    protected $processState;

    /** @var DaemonProcessDetails */
    protected $processDetails;

    /** @var LogProxy */
    protected $logProxy;

    /** @var RemoteApi */
    protected $remoteApi;

    /** @var RunningConfig */
    protected $runningConfig;

    /** @var GeneratedDowntimeGenerator */
    protected $generatedDowntimeGenerator;

    /** @var DowntimeRunner */
    protected $downtimeRunner;

    /** @var bool */
    protected $reloading = false;

    /** @var bool */
    protected $shuttingDown = false;

    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function run(LoopInterface $loop = null)
    {
        if ($ownLoop = ($loop === null)) {
            $loop = Loop::create();
        }
        $this->loop = $loop;
        $this->loop->futureTick(function () {
            $this->initialize();
        });
        if ($ownLoop) {
            $loop->run();
        }
    }

    protected function initialize()
    {
        $this->logger->notice('Starting up');
        $this->registerSignalHandlers($this->loop);
        $this->processState = new DaemonProcessState(Application::PROCESS_NAME);
        $this->jobRunner = new JobRunner($this->loop, $this->logger);
        $this->systemd = $this->optionallyInitializeSystemd();
        $this->processState->setSystemd($this->systemd);
        if ($this->systemd) {
            $this->systemd->setReady();
        }
        $this->setState('ready');
        $this->processDetails = $this
            ->initializeProcessDetails($this->systemd)
            ->registerProcessList($this->jobRunner->getProcessList());
        $this->logProxy = new LogProxy($this->logger);
        $this->jobRunner->forwardLog($this->logProxy);
        $this->generatedDowntimeGenerator = new GeneratedDowntimeGenerator($this->logger);
        $this->downtimeRunner = new DowntimeRunner($this->logger);
        $this->channelRunner = new InputAndChannelRunner($this->downtimeRunner, $this->loop, $this->logger);
        $this->daemonDb = $this->initializeDb(
            $this->processDetails,
            $this->processState,
            $this->dbResourceName
        );
        $this->runningConfig = new RunningConfig($this->logger);
        $this->runningConfig->watchRules(function ($rules) {
            // Disabled for now
            // $this->generatedDowntimeGenerator->triggerCalculation($rules);
            // $this->downtimeRunner->setDowntimeRules($rules);
        });
        $this->daemonDb
            ->register($this->jobRunner)
            ->register($this->logProxy)
            ->register($this->channelRunner)
            ->register($this->generatedDowntimeGenerator)
            ->register($this->runningConfig)
            ->register($this->downtimeRunner)
            ->run($this->loop);
        $this->prepareApi($this->channelRunner, $this->loop, $this->logger);
        $this->setState('running');
        $this->logger->notice('Daemon has been initialized');
    }

    /**
     * @param NotifySystemD|false $systemd
     * @return DaemonProcessDetails
     */
    protected function initializeProcessDetails($systemd)
    {
        if ($systemd && $systemd->hasInvocationId()) {
            $uuid = $systemd->getInvocationId();
        } else {
            try {
                $uuid = \bin2hex(Uuid::uuid4()->getBytes());
            } catch (Exception $e) {
                $uuid = 'deadc0de' . \substr(\md5(\getmypid()), 0, 24);
            }
        }
        $processDetails = new DaemonProcessDetails($uuid);
        if ($systemd) {
            $processDetails->set('running_with_systemd', 'y');
        }

        return $processDetails;
    }

    /**
     * @return false|NotifySystemD
     */
    protected function optionallyInitializeSystemd()
    {
        $systemd = NotifySystemD::ifRequired($this->loop);
        if ($systemd) {
            $this->logger->info(sprintf(
                "Started by systemd, notifying watchdog every %0.2Gs via %s",
                $systemd->getWatchdogInterval(),
                $systemd->getSocketPath()
            ));
        } else {
            $this->logger->debug('Running without systemd');
        }

        return $systemd;
    }

    protected function initializeDb(
        DaemonProcessDetails $processDetails,
        DaemonProcessState $processState,
        $dbResourceName = null
    ) {
        $db = new DaemonDb($processDetails, $this->logger);
        $db->on('state', function ($state, $level = null) use ($processState) {
            // TODO: level is sent but not used
            $processState->setComponentState('db', $state);
        });
        $db->on('schemaOutdated', function () {
            $this->reloading = true;
            $this->setState('reloading the main process');
            $this->daemonDb->disconnect()->then(function () {
                Process::restart();
            });
        });
        $db->on(DaemonDb::ON_SCHEMA_CHANGE, function ($startupSchema, $dbSchema) {
            $this->logger->info(sprintf(
                "DB schema version changed. Started with %d, DB has %d. Restarting.",
                $startupSchema,
                $dbSchema
            ));
            $this->loop->addTimer(0.3, function () {
                $this->reload();
            });
        });

        $db->setConfigWatch(
            $dbResourceName
            ? DbResourceConfigWatch::name($dbResourceName)
            : DbResourceConfigWatch::module('eventtracker')
        );

        return $db;
    }

    protected function prepareApi(InputAndChannelRunner $runner, LoopInterface $loop, LoggerInterface $logger)
    {
        $socketPath = Configuration::getSocketPath();
        $this->remoteApi = new RemoteApi($runner, $this->downtimeRunner, $this->runningConfig, $loop, $logger);
        StreamUtil::forwardEvents($this->remoteApi, $this, [RpcNamespaceProcess::ON_RESTART]);
        $this->remoteApi->run($socketPath, $loop);
    }

    protected function registerSignalHandlers(LoopInterface $loop)
    {
        $func = function ($signal) use (&$func) {
            $this->shutdownWithSignal($signal, $func);
        };
        $funcReload = function () {
            $this->reload();
        };
        $loop->addSignal(SIGHUP, $funcReload);
        $loop->addSignal(SIGINT, $func);
        $loop->addSignal(SIGTERM, $func);
    }

    protected function shutdownWithSignal($signal, &$func)
    {
        $this->loop->removeSignal($signal, $func);
        $this->shutdown();
    }

    public function reload()
    {
        if ($this->reloading) {
            $this->logger->error('Ignoring reload request, reload is already in progress');
            return;
        }
        $this->reloading = true;
        $this->setState('reloading the main process');
        $this->logger->notice('Going down for reload');
        $this->prepareShutdown()->then(function () {
            $this->loop->addTimer(0.1, function () {
                $this->loop->stop();
                Process::restart();
            });
        });
    }

    protected function shutdown()
    {
        $this->prepareShutdown()->then(function () {
            $this->logger->info('Shutdown completed');
            $this->loop->addTimer(0.1, function () {
                $this->loop->stop();
            });
        }, function (Exception $e) {
            $this->logger->error('Problem on shutdown: ' . $e->getMessage());
            $this->loop->addTimer(0.1, function () {
                $this->loop->stop();
            });
        });
    }

    protected function prepareShutdown()
    {
        if ($this->shuttingDown) {
            $this->logger->error('Ignoring shutdown request, shutdown is already in progress');
            return reject();
        }
        $this->logger->info('Shutting down');
        $this->shuttingDown = true;
        $this->setState('shutting down');
        return all([
            $this->daemonDb->disconnect()->then(function () {
                $this->logger->info('DB has been disconnected');
            }),
            $this->remoteApi->shutdown()->then(function () {
                $this->logger->info('Remote API has been closed');
                $this->remoteApi = null;
            }),
        ]);
    }

    protected function setState($state)
    {
        if ($this->processState) {
            $this->processState->setState($state);
        }

        return $this;
    }
}
