<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use Icinga\Module\Eventtracker\Engine\Input;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\UuidInterface;

abstract class SimpleInputConstructor implements Input
{
    use LoggerAwareTrait;
    use SettingsProperty;

    /** @var UuidInterface */
    protected $uuid;

    /** @var string */
    protected $name;

    /**
     * TODO: pass name, settings?
     */
    public function __construct(Settings $settings, UuidInterface $uuid, $name, LoggerInterface $logger = null)
    {
        if ($logger === null) {
            $this->setLogger(new NullLogger());
        } else {
            $this->setLogger($logger);
        }
        $this->uuid = $uuid;
        $this->name = $name;
        $this->setSettings($settings);
        $this->initialize();
    }

    protected function initialize()
    {
        // You might want to override this method
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
