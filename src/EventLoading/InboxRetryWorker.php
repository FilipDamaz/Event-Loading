<?php

declare(strict_types=1);

namespace App\EventLoading;

use App\EventLoading\Contracts\LoggerInterface;
use PDO;
use Throwable;

class InboxRetryWorker
{
    public const DEFAULT_BATCH_SIZE = 1000;
    public const DEFAULT_IDLE_SLEEP_MS = 50;

    private bool $running = true;
    private int $batchSize;
    private int $idleSleepMs;

    public function __construct(
        private PDO $pdo,
        private LoggerInterface $logger,
        int $batchSize = self::DEFAULT_BATCH_SIZE,
        int $idleSleepMs = self::DEFAULT_IDLE_SLEEP_MS,
    ) {
        $this->batchSize = max(1, min(1000, $batchSize));
        $this->idleSleepMs = max(1, $idleSleepMs);
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function run(): void
    {
        while ($this->running) {
            $didWork = $this->runOnce();
            if (!$didWork) {
                usleep($this->idleSleepMs * 1000);
            }
        }
    }

    public function runOnce(): bool
    {
        $this->pdo->beginTransaction();

        try {
            $rows = $this->fetchInboxRows($this->batchSize);

            if ($rows === []) {
                $this->pdo->rollBack();
                return false;
            }

            $maxBySource = [];
            $pairs = [];

            $insert = $this->pdo->prepare(
                'INSERT INTO events (source_name, event_id, payload) VALUES (:source, :id, :payload) '
                . 'ON CONFLICT (source_name, event_id) DO NOTHING',
            );

            foreach ($rows as $row) {
                $source = (string) $row['source_name'];
                $eventId = (int) $row['event_id'];
                $payload = (string) $row['payload'];

                $insert->execute([
                    'source' => $source,
                    'id' => $eventId,
                    'payload' => $payload,
                ]);

                $pairs[] = ['source' => $source, 'id' => $eventId];
                $maxBySource[$source] = max($maxBySource[$source] ?? 0, $eventId);
            }

            foreach ($maxBySource as $source => $maxId) {
                $this->advanceLastStoredId($source, $maxId);
            }

            $this->deleteInboxRows($pairs);

            $this->pdo->commit();

            $this->logger->info('Inbox retry worker moved events.', [
                'count' => count($rows),
            ]);

            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Inbox retry worker failed.', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * @return list<array{source_name: string, event_id: int, payload: string}>
     */
    private function fetchInboxRows(int $limit): array
    {
        // Edge cases:
        // - Parallel workers: SKIP LOCKED avoids blocking but may return an empty set.
        // - Partial batches: a worker can process fewer than $limit rows if others hold locks.
        $stmt = $this->pdo->prepare(
            'SELECT source_name, event_id, payload '
            . 'FROM event_inbox '
            . 'ORDER BY source_name, event_id '
            . 'LIMIT :limit '
            . 'FOR UPDATE SKIP LOCKED',
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array{source_name: string, event_id: int, payload: string}> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * @param array<int, array{source: string, id: int}> $pairs
     */
    private function deleteInboxRows(array $pairs): void
    {
        if ($pairs === []) {
            return;
        }

        $clauses = [];
        $params = [];

        foreach ($pairs as $index => $pair) {
            $sourceKey = 's' . $index;
            $idKey = 'e' . $index;
            $clauses[] = sprintf('(:%s, :%s)', $sourceKey, $idKey);
            $params[$sourceKey] = $pair['source'];
            $params[$idKey] = $pair['id'];
        }

        $sql = 'DELETE FROM event_inbox WHERE (source_name, event_id) IN (' . implode(', ', $clauses) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function advanceLastStoredId(string $sourceName, int $newLastStoredId): void
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
