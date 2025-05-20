<?php

namespace Icinga\Module\Eventtracker\Engine;

use Evenement\EventEmitterInterface;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Psr\Log\LoggerAwareInterface;
use Ramsey\Uuid\UuidInterface;

interface Task extends EventEmitterInterface, LoggerAwareInterface
{
    public function __construct(Settings $settings, UuidInterface $uuid, $name);

    public function getUuid(): UuidInterface;

    public function getName();

    public function getSettings(): Settings;

    public function applySettings(Settings $settings);

    public function run();

    public function start();
    public function stop();
    public function pause();
    public function resume();

    public static function getFormExtension(): FormExtension;

    public static function getLabel();

    public static function getDescription();
}
