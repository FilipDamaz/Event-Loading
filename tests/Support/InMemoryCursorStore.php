<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\EventLoading\Contracts\SourceCursorStoreInterface;

final class InMemoryCursorStore implements SourceCursorStoreInterface
{
    /** @var array<string, int> */
    public array $lastRequested = [];
    /** @var array<string, int> */
    public array $lastStored = [];

    public function getLastRequestedId(string $sourceName): int
    {
        return $this->lastRequested[$sourceName] ?? 0;
    }

    public function advanceLastRequestedId(string $sourceName, int $newLastRequestedId): void
    {
        $current = $this->lastRequested[$sourceName] ?? 0;
        $this->lastRequested[$sourceName] = max($current, $newLastRequestedId);
    }

    public function getLastStoredId(string $sourceName): int
    {
        return $this->lastStored[$sourceName] ?? 0;
    }

    public function advanceLastStoredId(string $sourceName, int $newLastStoredId): void
    {
        $current = $this->lastStored[$sourceName] ?? 0;
        $this->lastStored[$sourceName] = max($current, $newLastStoredId);
    }
}
