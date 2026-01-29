<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\EventLoading\Contracts\SourceRateLimiterInterface;

final class AllowAllRateLimiter implements SourceRateLimiterInterface
{
    public function tryAcquire(string $sourceName, int $minIntervalMs): bool
    {
        return true;
    }
}
