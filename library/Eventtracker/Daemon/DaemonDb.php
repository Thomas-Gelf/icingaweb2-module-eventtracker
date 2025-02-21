<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Evenement\EventEmitterTrait;
use Exception;
use gipfl\DbMigration\Migrations;
use gipfl\IcingaCliDaemon\DbResourceConfigWatch;
use gipfl\IcingaCliDaemon\RetryUnless;
use gipfl\ZfDb\Adapter\Adapter as ZfDb;
use gipfl\ZfDb\Adapter\Pdo\Mysql;
use Icinga\Application\Icinga;
use Icinga\Module\Eventtracker\Db\ZfDbConnectionFactory;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use RuntimeException;
use SplObjectStorage;
use function React\Promise\reject;
use function React\Promise\resolve;

class DaemonDb
{
    use EventEmitterTrait;

    public const ON_CONFIGURATION_LOADED = 'configuration loaded';
    public const ON_CONNECTED = 'connected';
    public const ON_CONNECTING = 'connecting';
    public const ON_LOCKED_BY_OTHER_INSTANCE = 'locked by other instance';
    public const ON_NO_CONFIGURATION = 'no configuration';
    public const ON_NO_SCHEMA = 'no schema';
    public const ON_SCHEMA_CHANGE = 'schemaChange';
    const TABLE_NAME = 'daemon_info';
    protected LoggerInterface $logger;

    /** @var LoopInterface */
    private $loop;

    /** @var ZfDb|Mysql */
    protected $db;

    /** @var DaemonProcessDetails */
    protected $details;

    /** @var DbBasedComponent[] */
    protected $registeredComponents = [];

    /** @var DbResourceConfigWatch|null */
    protected $configWatch;

    /** @var array|null */
    protected $dbConfig;

    /** @var RetryUnless|null */
    protected $pendingReconnection;

    /** @var ExtendedPromiseInterface|null */
    protected $pendingDisconnect;

    /** @var \React\EventLoop\TimerInterface */
    protected $refreshTimer;

    /** @var \React\EventLoop\TimerInterface */
    protected $schemaCheckTimer;

    /** @var int */
    protected $startupSchemaVersion;

    public function __construct(DaemonProcessDetails $details, LoggerInterface $logger, $dbConfig = null)
    {
        $this->details = $details;
        $this->dbConfig = $dbConfig;
        $this->logger = $logger;
    }

    public function register(DbBasedComponent $component)
    {
        $this->registeredComponents[] = $component;

        return $this;
    }

    public function setConfigWatch(DbResourceConfigWatch $configWatch)
    {
        $this->configWatch = $configWatch;
        $configWatch->notify(function ($config) {
            $this->disconnect()->then(function () use ($config) {
                return $this->onNewConfig($config);
            });
        });
        if ($this->loop) {
            $configWatch->run($this->loop);
        }

        return $this;
    }

    public function run(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->connect();
        $this->refreshTimer = $loop->addPeriodicTimer(3, function () {
            $this->refreshMyState();
        });
        $this->schemaCheckTimer = $loop->addPeriodicTimer(15, function () {
            $this->checkDbSchema();
        });
        $loop->addTimer(1, function () {
            $this->checkDbSchema();
        });
        if ($this->configWatch) {
            $this->configWatch->run($this->loop);
        }
    }

    protected function onNewConfig($config)
    {
        if ($config === null) {
            if ($this->dbConfig === null) {
                $this->logger->error('DB configuration is not valid');
            } else {
                $this->logger->error('DB configuration is no longer valid');
            }
            $this->emitStatus(self::ON_NO_CONFIGURATION);
            $this->dbConfig = $config;

            return resolve();
        } else {
            $this->emitStatus(self::ON_CONFIGURATION_LOADED);
            $this->dbConfig = $config;

            return $this->establishConnection($config);
        }
    }

    protected function establishConnection($config)
    {
        if ($this->db !== null) {
            $this->logger->error('Trying to establish a connection while being connected');
            return reject();
        }
        $callback = function () use ($config) {
            $this->reallyEstablishConnection($config);
        };
        $onSuccess = function () {
            $this->pendingReconnection = null;
            $this->onConnected();
        };
        if ($this->pendingReconnection) {
            $this->pendingReconnection->reset();
            $this->pendingReconnection = null;
        }
        $this->emitStatus(self::ON_CONNECTING);

        return $this->pendingReconnection = RetryUnless::succeeding($callback)
            ->setInterval(0.2)
            ->slowDownAfter(10, 10)
            ->run($this->loop)
            ->then($onSuccess)
            ;
    }

    protected function getMigrations(Mysql $db)
    {
        return new Migrations($db, Icinga::app()->getModuleManager()->getModuleDir(
            'eventtracker',
            '/schema'
        ), 'eventtracker_schema_migration');
    }

    protected function reallyEstablishConnection($config)
    {
        $db = ZfDbConnectionFactory::connection(Settings::fromSerialization($config));
        $db->getConnection();
        assert($db instanceof Mysql); // TODO: IDE hint only. Drop in case we're using PostgreSQL
        $migrations = $this->getMigrations($db);
        if (! $migrations->hasSchema()) {
            $this->emitStatus(self::ON_NO_SCHEMA, 'error');
            throw new RuntimeException('DB has no schema');
        }
        $this->wipeOrphanedInstances($db);
        if ($this->hasAnyOtherActiveInstance($db)) {
            $this->emitStatus(self::ON_LOCKED_BY_OTHER_INSTANCE, 'error');
            throw new RuntimeException('DB is locked by a running daemon instance');
        }
        $this->startupSchemaVersion = $migrations->getLastMigrationNumber();
        $this->details->set('schema_version', $this->startupSchemaVersion);

        $this->db = $db;
        $this->loop->futureTick(function () {
            $this->refreshMyState();
        });

        return $db;
    }

    protected function checkDbSchema()
    {
        if ($this->db === null) {
            return;
        }
        $migrations = $this->getMigrations($this->db);
        if ($migrations->hasPendingMigrations()) {
            $this->logger->warning('Schema is outdated, applying migrations');
            $count = $migrations->countPendingMigrations();
            $migrations->applyPendingMigrations();
            if ($count === 1) {
                $this->logger->notice("A pending DB migration has been applied");
            } else {
                $this->logger->notice("$count pending DB migrations have been applied");
            }
        }
        if ($this->schemaIsOutdated()) {
            $this->emit(self::ON_SCHEMA_CHANGE, [
                $this->getStartupSchemaVersion(),
                $this->getDbSchemaVersion()
            ]);
        }
    }

    protected function schemaIsOutdated(): bool
    {
        return $this->getStartupSchemaVersion() < $this->getDbSchemaVersion();
    }

    protected function getStartupSchemaVersion(): int
    {
        return $this->startupSchemaVersion;
    }

    protected function getDbSchemaVersion(): int
    {
        if ($this->db === null) {
            throw new RuntimeException(
                'Cannot determine DB schema version without an established DB connection'
            );
        }

        return $this->getMigrations($this->db)->getLastMigrationNumber();
    }

    protected function onConnected()
    {
        $this->emitStatus(self::ON_CONNECTED);
        $this->logger->notice('Connected to the database');
        foreach ($this->registeredComponents as $component) {
            $component->initDb($this->db);
        }
    }

    protected function reconnect(): ExtendedPromiseInterface
    {
        $promise = $this->disconnect()->then(function () {
            return $this->connect();
        }, function (Exception $e) {
            $this->logger->error('Disconnect failed. This should never happen: ' . $e->getMessage());
            exit(1);
        });

        assert($promise instanceof ExtendedPromiseInterface);
        return $promise;
    }

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function connect()
    {
        if ($this->db === null) {
            if ($this->dbConfig) {
                return $this->establishConnection($this->dbConfig);
            }
        }

        return resolve();
    }

    protected function stopRegisteredComponents(): ExtendedPromiseInterface
    {
        $pending = new Deferred();
        $pendingComponents = new SplObjectStorage();
        foreach ($this->registeredComponents as $component) {
            $pendingComponents->attach($component);
            $resolve = function () use ($pendingComponents, $component, $pending) {
                $pendingComponents->detach($component);
                if ($pendingComponents->count() === 0) {
                    $pending->resolve();
                }
            };
            // TODO: What should we do in case they don't?
            $component->stopDb()->then($resolve);
        }

        return $pending->promise();
    }

    public function disconnect(): ExtendedPromiseInterface
    {
        if (! $this->db) {
            return resolve();
        }
        if ($this->pendingDisconnect instanceof ExtendedPromiseInterface) {
            return $this->pendingDisconnect;
        }

        $this->eventuallySetStopped();
        $this->pendingDisconnect = $this->stopRegisteredComponents();

        try {
            if ($this->db) {
                $this->db->closeConnection();
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to disconnect: ' . $e->getMessage());
        }

        $pending = $this->pendingDisconnect->then(function () {
            $this->db = null;
            $this->pendingDisconnect = null;
        });
        assert($pending instanceof ExtendedPromiseInterface);

        return $pending;
    }

    protected function emitStatus($message, $level = 'info')
    {
        $this->emit('state', [$message, $level]);
    }

    protected function hasAnyOtherActiveInstance(ZfDb $db): bool
    {
        return (int) $db->fetchOne(
            $db->select()
                ->from(self::TABLE_NAME, 'COUNT(*)')
                ->where('ts_stopped IS NULL')
        ) > 0;
    }

    protected function wipeOrphanedInstances(ZfDb $db)
    {
        $db->delete(self::TABLE_NAME, 'ts_stopped IS NOT NULL');
        $db->delete(self::TABLE_NAME, $db->quoteInto(
            'instance_uuid_hex = ?',
            $this->details->getInstanceUuid()
        ));
        $count = $db->delete(
            self::TABLE_NAME,
            'ts_stopped IS NULL AND ts_last_update < ' . (
                DaemonUtil::timestampWithMilliseconds() - (60 * 1000)
            )
        );
        if ($count > 1) {
            $this->logger->warning("Removed $count orphaned daemon instance(s) from DB");
        }
    }

    protected function refreshMyState()
    {
        if ($this->db === null || $this->pendingReconnection || $this->pendingDisconnect) {
            return;
        }
        try {
            $updated = $this->db->update(
                self::TABLE_NAME,
                $this->details->getPropertiesToUpdate(),
                $this->db->quoteInto('instance_uuid_hex = ?', $this->details->getInstanceUuid())
            );

            if (! $updated) {
                $this->db->insert(
                    self::TABLE_NAME,
                    $this->details->getPropertiesToInsert()
                );
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->reconnect();
        }
    }

    protected function eventuallySetStopped()
    {
        try {
            if (! $this->db) {
                return;
            }
            $this->db->update(
                self::TABLE_NAME,
                ['ts_stopped' => DaemonUtil::timestampWithMilliseconds()],
                $this->db->quoteInto('instance_uuid_hex = ?', $this->details->getInstanceUuid())
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to update daemon info (setting ts_stopped): ' . $e->getMessage());
        }
    }
}
