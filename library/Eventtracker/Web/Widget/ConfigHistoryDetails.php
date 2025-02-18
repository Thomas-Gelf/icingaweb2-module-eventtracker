<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\WebActions;
use ipl\Html\HtmlDocument;
use RuntimeException;

class ConfigHistoryDetails extends HtmlDocument
{
    use TranslationHelper;
    public function __construct(WebActions $actions, $change)
    {

        $webAction = $actions->getByTableName($change->object_type);
        $singular = $webAction->singular;
        switch ($change->action) {
            case 'create':
                $actionName = sprintf(
                    $this->translate('%s %s has been created by %s'),
                    $singular,
                    $change->label,
                    $change->author
                );
                break;
            case 'modify':
                $actionName = sprintf(
                    $this->translate('%s %s has been modified by %s'),
                    $singular,
                    $change->label,
                    $change->author
                );
                break;
            case 'delete':
                $actionName = sprintf(
                    $this->translate('%s %s has been deleted by %s'),
                    $singular,
                    $change->label,
                    $change->author
                );
                break;
            default:
                throw new RuntimeException(sprintf('Invalid configuration change action: %s', $change->action));
        }

        $this->add($actionName);
    }
}
