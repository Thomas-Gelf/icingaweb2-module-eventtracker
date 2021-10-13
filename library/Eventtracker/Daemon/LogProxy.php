<?php

namespace Icinga\Module\Eventtracker\Daemon;

use gipfl\ZfDb\Adapter\Adapter as Db;
use function React\Promise\resolve;

class LogProxy implements DbBasedComponent
{
    protected $db;

    protected $server;

    protected $instanceUuid;

    protected $prefix = '';

    public function __construct($instanceUuid)
    {
        $this->instanceUuid = $instanceUuid;
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

        return resolve();
    }

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function stopDb()
    {
        $this->db = null;

        return resolve();
    }

    public function log($severity, $message)
    {
        Logger::$severity($this->prefix . $message);
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
