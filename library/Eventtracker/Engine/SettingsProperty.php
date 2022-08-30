<?php

namespace Icinga\Module\Eventtracker\Engine;

use Icinga\Module\Eventtracker\Modifier\Settings;

trait SettingsProperty
{
    protected $settings;

    protected function setSettings(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function getSettings(): Settings
    {
        return $this->settings;
    }
}
