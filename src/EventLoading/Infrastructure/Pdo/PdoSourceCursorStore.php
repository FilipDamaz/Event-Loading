<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure\Pdo;

use App\EventLoading\Contracts\SourceCursorStoreInterface;
use PDO;

final class PdoSourceCursorStore implements SourceCursorStoreInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getLastRequestedId(string $sourceName): int
    {
        $stmt = $this->pdo->prepare('SELECT last_requested_id FROM source_cursor WHERE source_name = :source');
        $stmt->execute(['source' => $sourceName]);
        $row = $stmt->fetch();

        return $row ? (int) $row['last_requested_id'] : 0;
    }

    public function advanceLastRequestedId(string $sourceName, int $newLastRequestedId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO source_cursor (source_name, last_requested_id, last_stored_id) '
            . 'VALUES (:source, :requested, 0) '
            . 'ON CONFLICT (source_name) DO UPDATE SET '
            . 'last_requested_id = GREATEST(source_cursor.last_requested_id, EXCLUDED.last_requested_id), '
            . 'updated_at = NOW()',
        );

        $stmt->execute([
            'source' => $sourceName,
            'requested' => $newLastRequestedId,
        ]);
    }

    public function getLastStoredId(string $sourceName): int
    {
        $stmt = $this->pdo->prepare('SELECT last_stored_id FROM source_cursor WHERE source_name = :source');
        $stmt->execute(['source' => $sourceName]);
        $row = $stmt->fetch();

        return $row ? (int) $row['last_stored_id'] : 0;
    }

    public function advanceLastStoredId(string $sourceName, int $newLastStoredId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO source_cursor (source_name, last_requested_id, last_stored_id) '
            . 'VALUES (:source, 0, :stored) '
            . 'ON CONFLICT (source_name) DO UPDATE SET '
            . 'last_stored_id = GREATEST(source_cursor.last_stored_id, EXCLUDED.last_stored_id), '
            . 'updated_at = NOW()',
        );

        $stmt->execute([
            'source' => $sourceName,
            'stored' => $newLastStoredId,
        ]);
    }
}
