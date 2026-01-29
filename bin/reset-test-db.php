<?php

declare(strict_types=1);

use App\EventLoading\Infrastructure\Pdo\PdoConnectionFactory;

require __DIR__ . '/../vendor/autoload.php';

$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl === false || $databaseUrl === '') {
    fwrite(STDERR, "DATABASE_URL is not set.\n");
    exit(1);
}

$factory = new PdoConnectionFactory();
$pdo = $factory->create($databaseUrl);

$schemaPath = __DIR__ . '/../sql/schema.sql';
$schemaSql = file_get_contents($schemaPath);
if ($schemaSql === false) {
    fwrite(STDERR, "Failed to read schema file: {$schemaPath}\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    $pdo->exec(
        "DO $$\n"
        . "DECLARE r RECORD;\n"
        . "BEGIN\n"
        . "  FOR r IN (SELECT tablename FROM pg_tables WHERE schemaname = 'public') LOOP\n"
        . "    EXECUTE 'DROP TABLE IF EXISTS ' || quote_ident(r.tablename) || ' CASCADE';\n"
        . "  END LOOP;\n"
        . "END $$;"
    );

    $pdo->exec($schemaSql);

    $pdo->commit();
    fwrite(STDOUT, "Test database reset complete.\n");
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Test database reset failed: " . $e->getMessage() . "\n");
    exit(1);
}
