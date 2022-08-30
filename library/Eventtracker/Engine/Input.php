<?php

namespace Icinga\Module\Eventtracker\Engine;

use Evenement\EventEmitterInterface;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Web\Form;
use Psr\Log\LoggerAwareInterface;
use Ramsey\Uuid\UuidInterface;
use React\EventLoop\LoopInterface;

interface Input extends EventEmitterInterface, LoggerAwareInterface
{
    public function __construct(Settings $settings, UuidInterface $uuid, $name);

    public function getUuid(): UuidInterface;

    public function getName();

    public function getSettings(): Settings;

    public function run(LoopInterface $loop);

    public function start();
    public function stop();
    public function pause();
    public function resume();

    public static function getFormExtension(): FormExtension;

    public static function getLabel();

    public static function getDescription();
}
