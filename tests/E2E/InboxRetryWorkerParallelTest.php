<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\EventLoading\InboxRetryWorker;
use App\EventLoading\Infrastructure\Pdo\PdoConnectionFactory;
use App\EventLoading\Infrastructure\StdoutLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InboxRetryWorkerParallelTest extends TestCase
{
    public function testParallelWorkersSkipLockedRows(): void
    {
        $databaseUrl = getenv('DATABASE_URL');
        if ($databaseUrl === false || $databaseUrl === '') {
            $this->markTestSkipped('Set DATABASE_URL to run this test.');
        }

        try {
            $pdoA = (new PdoConnectionFactory())->create($databaseUrl);
            $pdoB = (new PdoConnectionFactory())->create($databaseUrl);
        } catch (RuntimeException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $schema = file_get_contents(__DIR__ . '/../../sql/schema.sql');
        if ($schema === false) {
            $this->markTestSkipped('Cannot read schema.sql');
        }
        $pdoA->exec($schema);
        $pdoA->exec('TRUNCATE TABLE event_inbox, events, source_cursor, source_rate_limit, event_request_log');

        $pdoA->exec(
            "INSERT INTO event_inbox (source_name, event_id, payload) VALUES
            ('parallel-source', 1, '{\"payload\":{\"v\":1}}'),
            ('parallel-source', 2, '{\"payload\":{\"v\":2}}')",
        );

        $pdoA->beginTransaction();
        $lockStmt = $pdoA->prepare(
            'SELECT source_name, event_id FROM event_inbox WHERE source_name = :source FOR UPDATE',
        );
        $lockStmt->execute(['source' => 'parallel-source']);

        try {
            $workerB = new InboxRetryWorker($pdoB, new StdoutLogger());
            $processed = $workerB->runOnce();

            $this->assertFalse($processed);
            $count = (int) $pdoB->query('SELECT COUNT(*) FROM event_inbox')->fetchColumn();
            $this->assertSame(2, $count);

            $pdoA->rollBack();

            $processedAfter = $workerB->runOnce();
            $this->assertTrue($processedAfter);

            $countAfter = (int) $pdoB->query('SELECT COUNT(*) FROM event_inbox')->fetchColumn();
            $this->assertSame(0, $countAfter);
        } finally {
            $pdoB->exec('TRUNCATE TABLE event_inbox, events, source_cursor, source_rate_limit, event_request_log');
        }
    }
}
