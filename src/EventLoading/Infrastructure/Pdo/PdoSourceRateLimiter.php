<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure\Pdo;

use App\EventLoading\Contracts\SourceRateLimiterInterface;
use PDO;

final class PdoSourceRateLimiter implements SourceRateLimiterInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function tryAcquire(string $sourceName, int $minIntervalMs): bool
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'SELECT last_request_at FROM source_rate_limit WHERE source_name = :source FOR UPDATE',
            );
            $stmt->execute(['source' => $sourceName]);
            $row = $stmt->fetch();

            if ($row) {
                $check = $this->pdo->prepare(
                    'SELECT (EXTRACT(EPOCH FROM clock_timestamp()) * 1000) - ' .
                    '(EXTRACT(EPOCH FROM :last_request_at::timestamptz) * 1000) AS elapsed_ms',
                );
                $check->execute(['last_request_at' => $row['last_request_at']]);
                $elapsed = $check->fetchColumn();
                $elapsedMs = $elapsed !== false ? (int) round((float) $elapsed) : 0;

                if ($elapsedMs < $minIntervalMs) {
                    $this->pdo->rollBack();
                    return false;
                }

                $update = $this->pdo->prepare(
                    'UPDATE source_rate_limit SET last_request_at = clock_timestamp() WHERE source_name = :source',
                );
                $update->execute(['source' => $sourceName]);
            } else {
                $insert = $this->pdo->prepare(
                    'INSERT INTO source_rate_limit (source_name, last_request_at) VALUES (:source, clock_timestamp())',
                );
                $insert->execute(['source' => $sourceName]);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
