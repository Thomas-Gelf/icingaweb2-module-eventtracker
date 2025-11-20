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

    public function getName(): string;

    public function getSettings(): Settings;

    public function applySettings(Settings $settings): void;

    public function run(): void;

    public function start(): void;
    public function stop(): void;
    public function pause(): void;
    public function resume(): void;

    public static function getFormExtension(): FormExtension;

    public static function getLabel(): string;

    public static function getDescription(): string;
}
