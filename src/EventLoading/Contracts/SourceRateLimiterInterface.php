<?php

declare(strict_types=1);

namespace App\EventLoading\Contracts;

interface SourceRateLimiterInterface
{
    /**
     * Reserve a request slot if the minimum interval has elapsed.
     * Returns true if the caller may perform a request now.
     */
    public function tryAcquire(string $sourceName, int $minIntervalMs): bool;
}
