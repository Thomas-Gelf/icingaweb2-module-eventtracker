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
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimePeriodRunner;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRunner;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Ramsey\Uuid\Uuid;
use React\Stream\Util as StreamUtil;
use function React\Promise\all;
use function React\Promise\reject;

class BackgroundDaemon implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected DowntimePeriodRunner $downtimePeriodRunner;

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
    protected ?RemoteApi $remoteApi = null;
    protected ?DowntimeRunner $downtimeRunner = null;
    protected bool $reloading = false;
    protected bool $shuttingDown = false;

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function run()
    {
        Loop::futureTick(fn () => $this->initialize());
    }

    protected function initialize()
    {
        $this->logger->notice('Starting up');
        $this->registerSignalHandlers();
        $this->processState = new DaemonProcessState(Application::PROCESS_NAME);
        $this->jobRunner = new JobRunner($this->logger);
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
        $this->downtimePeriodRunner = new DowntimePeriodRunner($this->logger);
        $this->downtimeRunner = new DowntimeRunner($this->downtimePeriodRunner, $this->logger);
        $this->channelRunner = new InputAndChannelRunner($this->downtimeRunner, $this->logger);
        $this->daemonDb = $this->initializeDb(
            $this->processDetails,
            $this->processState,
            $this->dbResourceName
        );
        $this->daemonDb
            ->register($this->jobRunner)
            ->register($this->logProxy)
            ->register($this->channelRunner)
            ->register($this->downtimePeriodRunner)
            ->register($this->downtimeRunner)
            ->run();
        $this->prepareApi($this->channelRunner, $this->daemonDb, $this->logger);
        $this->setState('running');
        $this->logger->notice('Daemon has been initialized');
    }

    /**
     * @param NotifySystemD|false $systemd
     */
    protected function initializeProcessDetails($systemd): DaemonProcessDetails
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
        $systemd = NotifySystemD::ifRequired(Loop::get());
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
    ): DaemonDb {
        $db = new DaemonDb($processDetails, $this->logger);
        $db->on('state', function ($state, $level = null) use ($processState) {
            // TODO: level is sent but not used
            $processState->setComponentState('db', $state);
        });
        $db->on('schemaOutdated', function () {
            $this->reloading = true;
            $this->setState('reloading the main process');
            $this->daemonDb->disconnect()->then(fn () => Process::restart());
        });
        $db->on(DaemonDb::ON_SCHEMA_CHANGE, function ($startupSchema, $dbSchema) {
            $this->logger->info(sprintf(
                "DB schema version changed. Started with %d, DB has %d. Restarting.",
                $startupSchema,
                $dbSchema
            ));
            Loop::addTimer(0.3, fn () => $this->reload());
        });

        $db->setConfigWatch(
            $dbResourceName
            ? DbResourceConfigWatch::name($dbResourceName)
            : DbResourceConfigWatch::module('eventtracker')
        );

        return $db;
    }

    protected function prepareApi(InputAndChannelRunner $runner, DaemonDb $daemonDb, LoggerInterface $logger)
    {
        $socketPath = Configuration::getSocketPath();
        $this->remoteApi = new RemoteApi($runner, $this->downtimeRunner, $this->daemonDb, $logger);
        StreamUtil::forwardEvents($this->remoteApi, $this, [RpcNamespaceProcess::ON_RESTART]);
        $this->remoteApi->run($socketPath);
    }

    protected function registerSignalHandlers()
    {
        $func = function ($signal) use (&$func) {
            $this->shutdownWithSignal($signal, $func);
        };
        $funcReload = function () {
            $this->reload();
        };
        Loop::addSignal(SIGHUP, $funcReload);
        Loop::addSignal(SIGINT, $func);
        Loop::addSignal(SIGTERM, $func);
    }

    protected function shutdownWithSignal($signal, &$func)
    {
        Loop::removeSignal($signal, $func);
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
            Loop::addTimer(0.1, function () {
                Loop::stop();
                Process::restart();
            });
        });
    }

    protected function shutdown()
    {
        $this->prepareShutdown()->then(function () {
            $this->logger->info('Shutdown completed');
            Loop::addTimer(0.1, fn () => Loop::stop());
        }, function (Exception $e) {
            $this->logger->error('Problem on shutdown: ' . $e->getMessage());
            Loop::addTimer(0.1, fn () => Loop::stop());
        });
    }

    protected function prepareShutdown()
    {
        if ($this->shuttingDown) {
            return reject(new Exception('Ignoring shutdown request, shutdown is already in progress'));
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

    protected function setState(string $state): void
    {
        if ($this->processState) {
            $this->processState->setState($state);
        }
    }
}
