<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Psr\Log\LoggerInterface;

class LogProxy implements DbBasedComponent
{
    use SimpleDbBasedComponent;

    protected string $prefix = '';
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
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
