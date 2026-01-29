<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure\Pdo;

use App\EventLoading\Contracts\SourceLeaseInterface;
use App\EventLoading\Contracts\SourceLeaseManagerInterface;
use PDO;
use RuntimeException;

final class PdoSourceLeaseManager implements SourceLeaseManagerInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function acquire(string $sourceName, int $ttlMs): ?SourceLeaseInterface
    {
        [$key1, $key2] = $this->hashKeys($sourceName);

        $stmt = $this->pdo->prepare('SELECT pg_try_advisory_lock(:k1, :k2) AS locked');
        $stmt->execute(['k1' => $key1, 'k2' => $key2]);
        $row = $stmt->fetch();

        if (!$row || $row['locked'] !== true && $row['locked'] !== 't') {
            return null;
        }

        return new class ($this->pdo, $key1, $key2) implements SourceLeaseInterface {
            public function __construct(private PDO $pdo, private int $key1, private int $key2)
            {
            }

            public function release(): void
            {
                $stmt = $this->pdo->prepare('SELECT pg_advisory_unlock(:k1, :k2)');
                $stmt->execute(['k1' => $this->key1, 'k2' => $this->key2]);
            }
        };
    }

    /**
     * @return array{int, int}
     */
    private function hashKeys(string $sourceName): array
    {
        $hash = hash('sha256', $sourceName, true);
        $parts = unpack('N2', substr($hash, 0, 8));
        if (!is_array($parts) || !isset($parts[1], $parts[2])) {
            throw new RuntimeException('Failed to hash source name.');
        }

        return [(int) $parts[1], (int) $parts[2]];
    }
}
