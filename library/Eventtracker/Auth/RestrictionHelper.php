<?php

namespace Icinga\Module\Eventtracker\Auth;

use gipfl\ZfDb\Expr;
use gipfl\ZfDb\Select;
use Icinga\Authentication\Auth;
use Ramsey\Uuid\Uuid;

class RestrictionHelper
{
    public static function applyInputFilters(Select $query, Auth $auth): void
    {
        if ($restrictions = $auth->getRestrictions('eventtracker/ignoreInputs')) {
            foreach ($restrictions as $restriction) {
                foreach (preg_split('/\s*,\s*/', $restriction) as $value) {
                    $query->where(
                        '(i.input_uuid IS NULL OR i.input_uuid != ?)',
                        new Expr('0x' . bin2hex(Uuid::fromString($value)->getBytes()))
                    );
                }
            }
        }
        if ($restrictions = $auth->getRestrictions('eventtracker/filterInputs')) {
            $inputs = [];
            foreach ($restrictions as $restriction) {
                foreach (preg_split('/\s*,\s*/', $restriction) as $value) {
                    $inputs[] = new Expr('0x' . bin2hex(Uuid::fromString($value)->getBytes()));
                }
            }
            if (! empty($inputs)) {
                $query->where('i.input_uuid IN (?)', $inputs);
            }
        }
    }
}
