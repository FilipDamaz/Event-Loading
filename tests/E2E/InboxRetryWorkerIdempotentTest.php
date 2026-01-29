<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\EventLoading\InboxRetryWorker;
use App\EventLoading\Infrastructure\Pdo\PdoConnectionFactory;
use App\EventLoading\Infrastructure\StdoutLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InboxRetryWorkerIdempotentTest extends TestCase
{
    public function testWorkerIsIdempotent(): void
    {
        $databaseUrl = getenv('DATABASE_URL');
        if ($databaseUrl === false || $databaseUrl === '') {
            $this->markTestSkipped('Set DATABASE_URL to run this test.');
        }

        try {
            $pdo = (new PdoConnectionFactory())->create($databaseUrl);
        } catch (RuntimeException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $schema = file_get_contents(__DIR__ . '/../../sql/schema.sql');
        if ($schema === false) {
            $this->markTestSkipped('Cannot read schema.sql');
        }
        $pdo->exec($schema);
        $pdo->exec('TRUNCATE TABLE event_inbox, events, source_cursor, source_rate_limit, event_request_log');

        $pdo->exec(
            "INSERT INTO event_inbox (source_name, event_id, payload) VALUES
            ('dup-source', 1, '{\"payload\":{\"v\":1}}'),
            ('dup-source', 2, '{\"payload\":{\"v\":2}}')",
        );

        $worker = new InboxRetryWorker($pdo, new StdoutLogger());
        $first = $worker->runOnce();
        $this->assertTrue($first);

        $eventsCount = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
        $inboxCount = (int) $pdo->query('SELECT COUNT(*) FROM event_inbox')->fetchColumn();
        $this->assertSame(2, $eventsCount);
        $this->assertSame(0, $inboxCount);

        $second = $worker->runOnce();
        $this->assertFalse($second);

        $eventsCountAfter = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
        $this->assertSame(2, $eventsCountAfter);
    }
}
