<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure\Pdo;

use App\EventLoading\Contracts\EventStorageInterface;
use App\EventLoading\Model\EventInterface;
use JsonException;
use PDO;

final class PdoEventStorage implements EventStorageInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function store(string $sourceName, array $events): void
    {
        if ($events === []) {
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO events (source_name, event_id, payload) VALUES (:source, :id, :payload) '
                . 'ON CONFLICT (source_name, event_id) DO NOTHING',
            );

            foreach ($events as $event) {
                $stmt->execute([
                    'source' => $sourceName,
                    'id' => $event->getId(),
                    'payload' => $this->encodePayload($event),
                ]);
            }

            $this->pdo->commit();
        } catch (JsonException $e) {
            $this->pdo->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @throws JsonException
     */
    private function encodePayload(EventInterface $event): string
    {
        return json_encode($event->getPayload(), JSON_THROW_ON_ERROR);
    }
}
