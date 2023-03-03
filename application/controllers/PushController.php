<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Icinga\Exception\NotFoundError;

/**
 * @deprecated use the msend module
 */
class PushController extends Controller
{
    protected $requiresAuthentication = false;

    public function testmsendAction()
    {
        throw new NotFoundError(
            'Not found, please use the msend module ("/msend/test" instead of "/eventtracker/push/testmsend"'
        );
    }

    public function msendAction()
    {
        throw new NotFoundError(
            'Not found, please use the msend module ("/msend" instead of "/eventtracker/push/msend"'
        );
    }
}
