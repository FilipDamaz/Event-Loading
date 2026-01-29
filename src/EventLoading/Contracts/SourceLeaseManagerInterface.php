<?php

declare(strict_types=1);

namespace App\EventLoading\Contracts;

interface SourceLeaseManagerInterface
{
    /**
     * Try to acquire an exclusive lease for a source.
     *
     * @return SourceLeaseInterface|null Null when lease not acquired.
     */
    public function acquire(string $sourceName, int $ttlMs): ?SourceLeaseInterface;
}
