<?php

namespace Icinga\Module\Eventtracker\Daemon;

use gipfl\ZfDb\Adapter\Adapter as Db;
use Psr\Log\LoggerInterface;
use function React\Promise\resolve;

class LogProxy implements DbBasedComponent
{
    protected $db;

    protected $prefix = '';

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @param Db $db
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function initDb(Db $db)
    {
        $this->db = $db;

        return resolve(null);
    }

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function stopDb()
    {
        $this->db = null;

        return resolve(null);
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function logNotification(string $level, string $message, array $context = [])
    {
        $this->logger->log($level, $this->prefix . $message, $context);
        /*
        // Not yet
        try {
            if ($this->db) {
                $this->db->insert('director_daemonlog', [
                    // environment/installation/db?
                    'instance_uuid' => $this->instanceUuid,
                    'ts_create'     => DaemonUtil::timestampWithMilliseconds(),
                    'level'         => $severity,
                    'message'       => $message,
                ]);
            }
        } catch (Exception $e) {
            Logger::error($e->getMessage());
        }
        */
    }
}
