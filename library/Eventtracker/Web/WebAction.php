<?php

namespace Icinga\Module\Eventtracker\Web;

use Icinga\Module\Eventtracker\Engine\Registry;
use Icinga\Module\Eventtracker\Web\Form\UuidObjectForm;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;

class WebAction
{
    /** @var string */
    public $name;
    /** @var string */
    public $singular;
    /** @var string */
    public $plural;
    /** @var string */
    public $description;
    /** @var string */
    public $table;
    /** @var string */
    public $listUrl;
    /** @var string */
    public $url;
    /** @var string */
    public $icon;
    /** @var class-string<BaseTable> */
    public $tableClass;
    /** @var class-string<UuidObjectForm> */
    public $formClass;
    /** @var ?class-string<Registry> */
    public $registry;

    public static function create(array $properties): WebAction
    {
        // To be replaced with constructor with named parameters, once we require PHP 8.x
        $self = new WebAction;
        foreach ($properties as $key => $value) {
            $self->$key = $value;
        }

        return $self;
    }
}
