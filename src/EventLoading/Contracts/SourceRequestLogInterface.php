<?php

declare(strict_types=1);

namespace App\EventLoading\Contracts;

interface SourceRequestLogInterface
{
    /**
     * Reserve a request for a given source and afterId. Returns a token id or null if already reserved.
     */
    public function reserve(string $sourceName, int $afterId, int $limit): ?int;

    /**
     * Mark the request as fetched and stored in inbox (before final storage).
     */
    public function markInboxOnly(int $requestId, int $maxId): void;

    /**
     * Mark the request as successfully stored.
     */
    public function markSucceeded(int $requestId, int $maxId): void;

    /**
     * Mark the request as failed. The reservation remains to avoid re-fetching.
     */
    public function markFailed(int $requestId, string $error): void;

    /**
     * Release a reservation (e.g., when fetch returns no events).
     */
    public function release(int $requestId): void;
}
