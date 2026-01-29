<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure\Pdo;

use App\EventLoading\Contracts\SourceRequestLogInterface;
use PDO;

final class PdoSourceRequestLog implements SourceRequestLogInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function reserve(string $sourceName, int $afterId, int $limit): ?int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO event_request_log (source_name, after_id, limit_count, status) '
            . 'VALUES (:source, :after_id, :limit_count, :status) '
            . 'ON CONFLICT (source_name, after_id) DO NOTHING',
        );

        $stmt->execute([
            'source' => $sourceName,
            'after_id' => $afterId,
            'limit_count' => $limit,
            'status' => 'reserved',
        ]);

        $id = $this->pdo->lastInsertId();
        if ($id === '0' || $id === '' || $id === false) {
            return null;
        }

        return (int) $id;
    }

    public function markSucceeded(int $requestId, int $maxId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE event_request_log SET status = :status, max_id = :max_id, updated_at = NOW() '
            . 'WHERE id = :id',
        );
        $stmt->execute([
            'status' => 'succeeded',
            'max_id' => $maxId,
            'id' => $requestId,
        ]);
    }

    public function markInboxOnly(int $requestId, int $maxId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE event_request_log SET status = :status, max_id = :max_id, updated_at = NOW() '
            . 'WHERE id = :id',
        );
        $stmt->execute([
            'status' => 'inbox_only',
            'max_id' => $maxId,
            'id' => $requestId,
        ]);
    }

    public function markFailed(int $requestId, string $error): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE event_request_log SET status = :status, error = :error, updated_at = NOW() '
            . 'WHERE id = :id',
        );
        $stmt->execute([
            'status' => 'failed',
            'error' => $error,
            'id' => $requestId,
        ]);
    }

    public function release(int $requestId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM event_request_log WHERE id = :id');
        $stmt->execute(['id' => $requestId]);
    }
}
