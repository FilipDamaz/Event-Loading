<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure\Pdo;

use PDO;
use RuntimeException;

final class PdoConnectionFactory
{
    public function create(string $databaseUrl): PDO
    {
        $parts = parse_url($databaseUrl);
        if ($parts === false) {
            throw new RuntimeException('Invalid DATABASE_URL.');
        }

        $scheme = $parts['scheme'] ?? '';
        if ($scheme !== 'postgresql' && $scheme !== 'postgres') {
            throw new RuntimeException('Only PostgreSQL DATABASE_URL is supported.');
        }

        $host = $parts['host'] ?? 'localhost';
        $port = $parts['port'] ?? 5432;
        $user = $parts['user'] ?? 'app';
        $pass = $parts['pass'] ?? '';
        $db = isset($parts['path']) ? ltrim($parts['path'], '/') : 'app';

        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $db);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }
}
