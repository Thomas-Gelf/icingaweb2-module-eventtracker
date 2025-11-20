<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Engine\SimpleRegistry;

class ActionRegistry extends SimpleRegistry
{
    /** @var array<string, class-string<Action>> */
    protected array $implementations = [
        'command' => CommandAction::class,
        'mail'    => MailAction::class,
        'soap'    => SoapAction::class,
    ];
}
