<?php

namespace Icinga\Module\Eventtracker\Engine\Bucket;

use Icinga\Module\Eventtracker\Engine\SimpleRegistry;

class BucketRegistry extends SimpleRegistry
{
    protected $implementations = [
        'RateLimit' => RateLimitBucket::class,
    ];
}
