<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use Icinga\Module\Eventtracker\Engine\SimpleRegistry;

class ActionRegistry extends SimpleRegistry
{
    protected $implementations = [
        'command' => CommandAction::class,
        'mail'    => MailAction::class,
        'soap'    => SoapAction::class,
        'iet'     => IetAction::class
    ];
}
