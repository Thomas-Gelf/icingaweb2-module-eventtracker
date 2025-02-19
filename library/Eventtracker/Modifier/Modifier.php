<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use ipl\Html\Form;
use ipl\Html\ValidHtml;

interface Modifier
{
    public function __construct(Settings $settings);

    public function getSettings(): Settings;

    public static function getName(): string;

    public function transform(object $object, string $propertyName);

    /**
     * @param string $propertyName
     * @return string|ValidHtml
     */
    public function describe(string $propertyName);

    public static function extendSettingsForm(ChannelRuleForm $form): void;
}
