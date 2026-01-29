<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\EventLoading\Contracts\SourceLeaseInterface;
use App\EventLoading\Contracts\SourceLeaseManagerInterface;

final class AllowAllLeaseManager implements SourceLeaseManagerInterface
{
    public function acquire(string $sourceName, int $ttlMs): ?SourceLeaseInterface
    {
        return new class () implements SourceLeaseInterface {
            public function release(): void
            {
            }
        };
    }
}
