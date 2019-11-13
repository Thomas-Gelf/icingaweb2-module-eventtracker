<?php

namespace Icinga\Module\Eventtracker\Scom;

class Scom
{
    const RESOLUTION_STATE_NEW = 0;
    const RESOLUTION_STATE_AWAITING_EVIDENCE = 247;
    const RESOLUTION_STATE_ASSIGNED_TO_ENGINEERING = 248;
    const RESOLUTION_STATE_ACKNOWLEDGE = 249;
    const RESOLUTION_STATE_SCHEDULED = 250;
    const RESOLUTION_STATE_RESOLVED = 254;
    const RESOLUTION_STATE_CLOSED = 255;
    // https://docs.microsoft.com/en-us/system-center/scom/manage-alert-set-resolution-states?view=sc-om-2019
    const DEFAULT_RESOLUTION_STATES = [
        self::RESOLUTION_STATE_NEW                     => 'New',
        self::RESOLUTION_STATE_AWAITING_EVIDENCE       => 'Awaiting Evidence',
        self::RESOLUTION_STATE_ASSIGNED_TO_ENGINEERING => 'Assigned to Engineering',
        self::RESOLUTION_STATE_ACKNOWLEDGE             => 'Acknowledge',
        self::RESOLUTION_STATE_SCHEDULED               => 'Scheduled',
        self::RESOLUTION_STATE_RESOLVED                => 'Resolved',
        self::RESOLUTION_STATE_CLOSED                  => 'Closed',
    ];
}
