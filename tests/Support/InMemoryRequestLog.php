<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\EventLoading\Contracts\SourceRequestLogInterface;

final class InMemoryRequestLog implements SourceRequestLogInterface
{
    /** @var array<string, int> */
    private array $reservations = [];
    private int $nextId = 1;

    public function reserve(string $sourceName, int $afterId, int $limit): ?int
    {
        $key = $sourceName . ':' . $afterId;
        if (isset($this->reservations[$key])) {
            return null;
        }
        $id = $this->nextId++;
        $this->reservations[$key] = $id;
        return $id;
    }

    public function markSucceeded(int $requestId, int $maxId): void
    {
    }

    public function markInboxOnly(int $requestId, int $maxId): void
    {
    }

    public function markFailed(int $requestId, string $error): void
    {
    }

    public function release(int $requestId): void
    {
        $key = array_search($requestId, $this->reservations, true);
        if ($key !== false) {
            unset($this->reservations[$key]);
        }
    }
}
