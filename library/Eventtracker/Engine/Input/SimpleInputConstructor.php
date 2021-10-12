<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use Icinga\Module\Eventtracker\Engine\Input;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Ramsey\Uuid\UuidInterface;

abstract class SimpleInputConstructor implements Input
{
    use SettingsProperty;

    /** @var UuidInterface */
    protected $uuid;

    /** @var string */
    protected $name;

    /**
     * TODO: pass name, settings?
     */
    public function __construct(Settings $settings, UuidInterface $uuid, $name)
    {
        $this->uuid = $uuid;
        $this->name = $name;
        $this->setSettings($settings);
        $this->initialize();
    }

    protected function initialize()
    {
        // You might want to override this method
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function getName()
    {
        return $this->name;
    }
}
