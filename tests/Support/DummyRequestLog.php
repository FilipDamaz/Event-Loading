<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\EventLoading\Contracts\SourceRequestLogInterface;

final class DummyRequestLog implements SourceRequestLogInterface
{
    public function __construct(private int $reserveId = 1)
    {
    }

    public function reserve(string $sourceName, int $afterId, int $limit): ?int
    {
        return $this->reserveId;
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
    }
}
