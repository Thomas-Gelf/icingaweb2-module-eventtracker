<?php

namespace Icinga\Module\Eventtracker\Engine\Bucket;

use Icinga\Module\Eventtracker\Engine\SimpleRegistry;

class BucketRegistry extends SimpleRegistry
{
    /** @var array<string, class-string<BucketInterface>> */
    protected array $implementations = [
        'RateLimit' => RateLimitBucket::class,
    ];
}
