<?php

declare(strict_types=1);

namespace App\EventLoading\Contracts;

interface SourceCursorStoreInterface
{
    public function getLastRequestedId(string $sourceName): int;

    /**
     * Persist the largest id ever requested from the source. Must be monotonic.
     */
    public function advanceLastRequestedId(string $sourceName, int $newLastRequestedId): void;

    public function getLastStoredId(string $sourceName): int;

    /**
     * Persist the largest id successfully stored for the source. Must be monotonic.
     */
    public function advanceLastStoredId(string $sourceName, int $newLastStoredId): void;
}
