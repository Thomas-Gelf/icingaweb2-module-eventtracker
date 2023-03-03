<?php

namespace Icinga\Module\Eventtracker\Web;

class WebAction
{
    public $singular;
    public $plural;
    public $description;
    public $table;
    public $listUrl;
    public $url;
    public $icon;
    public $tableClass;
    public $formClass;
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
