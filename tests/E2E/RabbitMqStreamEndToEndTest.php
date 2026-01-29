<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\EventLoading\Contracts\EventSourceInterface;
use App\EventLoading\EventLoader;
use App\EventLoading\Handler\EventBatchHandler;
use App\EventLoading\InboxRetryWorker;
use App\EventLoading\Infrastructure\Pdo\PdoConnectionFactory;
use App\EventLoading\Infrastructure\Pdo\PdoEventInbox;
use App\EventLoading\Infrastructure\Pdo\PdoEventStorage;
use App\EventLoading\Infrastructure\Pdo\PdoSourceCursorStore;
use App\EventLoading\Infrastructure\Pdo\PdoSourceLeaseManager;
use App\EventLoading\Infrastructure\Pdo\PdoSourceRateLimiter;
use App\EventLoading\Infrastructure\Pdo\PdoSourceRequestLog;
use App\EventLoading\Infrastructure\RabbitMq\PhpAmqplibStreamClient;
use App\EventLoading\Infrastructure\RabbitMq\RabbitMqStreamEventSource;
use App\EventLoading\Infrastructure\StdoutLogger;
use App\EventLoading\Model\EventInterface;
use App\EventLoading\Validation\EventBatchValidator;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StopController
{
    private ?EventLoader $loader = null;

    public function setLoader(EventLoader $loader): void
    {
        $this->loader = $loader;
    }

    public function stop(): void
    {
        if ($this->loader !== null) {
            $this->loader->stop();
        }
    }
}

final class StoppingEventSource implements EventSourceInterface
{
    public function __construct(private EventSourceInterface $inner, private StopController $stopController)
    {
    }

    public function getName(): string
    {
        return $this->inner->getName();
    }

    public function fetchEvents(int $afterId, int $limit): array
    {
        $events = $this->inner->fetchEvents($afterId, $limit);
        $this->stopController->stop();
        return $events;
    }
}

final class RabbitMqStreamEndToEndTest extends TestCase
{
    public function testEndToEndWithRabbitMqStreams(): void
    {
        $rabbitDsn = getenv('RABBITMQ_DSN');
        $databaseUrl = getenv('DATABASE_URL');

        if ($rabbitDsn === false || $rabbitDsn === '' || $databaseUrl === false || $databaseUrl === '') {
            $this->markTestSkipped('Set RABBITMQ_DSN and DATABASE_URL to run this test.');
        }

        $streamName = 'e2e-stream-' . bin2hex(random_bytes(4));
        $connectionForCleanup = null;
        $channelForCleanup = null;

        try {
            [$connectionForCleanup, $channelForCleanup] = $this->publishStreamMessages($rabbitDsn, $streamName, 3);
        } catch (RuntimeException $e) {
            $this->markTestSkipped('RabbitMQ not available: ' . $e->getMessage());
        }

        try {
            $client = new PhpAmqplibStreamClient($rabbitDsn, 10000);
            $source = new RabbitMqStreamEventSource(
                $client,
                $streamName,
                'rabbitmq-e2e',
            );

            $events = $source->fetchEvents(-1, 10);
            $this->assertGreaterThan(0, count($events));
            $this->assertTrue($this->isMonotonicIncreasing($events));

            try {
                $pdo = (new PdoConnectionFactory())->create($databaseUrl);
            } catch (RuntimeException $e) {
                $this->markTestSkipped('Database not available: ' . $e->getMessage());
            }
            $this->applySchema($pdo);
            $this->truncateTables($pdo);
            $this->seedCursor($pdo, 'rabbitmq-e2e', -1, -1);

            $cursorStore = new PdoSourceCursorStore($pdo);
            $rateLimiter = new PdoSourceRateLimiter($pdo);
            $leaseManager = new PdoSourceLeaseManager($pdo);
            $requestLog = new PdoSourceRequestLog($pdo);
            $inbox = new PdoEventInbox($pdo);
            $storage = new PdoEventStorage($pdo);
            $logger = new StdoutLogger();

            $start = microtime(true);
            $attempts = 0;
            $maxSeconds = 5.0;
            $inboxCount = 0;

            while ((microtime(true) - $start) < $maxSeconds) {
                $attempts++;
                $this->runLoaderOnce(
                    $source,
                    $leaseManager,
                    $rateLimiter,
                    $cursorStore,
                    $requestLog,
                    $inbox,
                    $storage,
                    $logger,
                );

                $inboxCount = (int) $pdo->query('SELECT COUNT(*) FROM event_inbox')->fetchColumn();
                if ($inboxCount >= 3) {
                    break;
                }

                usleep(250_000);
            }

            if ($inboxCount < 3) {
                $this->fail(sprintf('Inbox not filled in time (count=%d, attempts=%d).', $inboxCount, $attempts));
            }

            $inboxCount = (int) $pdo->query('SELECT COUNT(*) FROM event_inbox')->fetchColumn();
            $eventsCount = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();

            $this->assertSame(3, $inboxCount);
            $this->assertSame(3, $eventsCount);

            $worker = new InboxRetryWorker($pdo, $logger);
            $worker->runOnce();

            $inboxCountAfter = (int) $pdo->query('SELECT COUNT(*) FROM event_inbox')->fetchColumn();
            $this->assertSame(0, $inboxCountAfter);
        } finally {
            if ($channelForCleanup !== null) {
                try {
                    $channelForCleanup->queue_delete($streamName);
                } catch (\Throwable $e) {
                }
                $channelForCleanup->close();
            }
            if ($connectionForCleanup !== null) {
                $connectionForCleanup->close();
            }
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // no-op: cleanup handled in test to ensure queue is removed even on failure
    }

    /** @return array{AMQPStreamConnection, \PhpAmqpLib\Channel\AMQPChannel} */
    private function publishStreamMessages(string $dsn, string $streamName, int $count): array
    {
        $conn = $this->createConnection($dsn);
        $channel = $conn->channel();

        $channel->queue_declare(
            $streamName,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(['x-queue-type' => 'stream']),
        );

        $channel->confirm_select();

        for ($i = 0; $i < $count; $i++) {
            $body = json_encode(['payload' => ['index' => $i]], JSON_THROW_ON_ERROR);
            $message = new AMQPMessage($body, ['delivery_mode' => 2]);
            $channel->basic_publish($message, '', $streamName);
        }

        $channel->wait_for_pending_acks_returns(5);
        usleep(200_000);

        return [$conn, $channel];
    }

    private function createConnection(string $dsn): AMQPStreamConnection
    {
        $parts = parse_url($dsn);
        if ($parts === false) {
            throw new RuntimeException('Invalid RABBITMQ_DSN.');
        }

        $host = $parts['host'] ?? 'localhost';
        $port = (int) ($parts['port'] ?? 5672);
        $user = $parts['user'] ?? 'guest';
        $pass = $parts['pass'] ?? 'guest';
        $vhost = '/';

        if (isset($parts['path']) && $parts['path'] !== '') {
            $vhost = rawurldecode(ltrim($parts['path'], '/'));
            if ($vhost === '') {
                $vhost = '/';
            }
        }

        try {
            return new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to connect to RabbitMQ.', 0, $e);
        }
    }

    /** @param list<EventInterface> $events */
    private function isMonotonicIncreasing(array $events): bool
    {
        $prev = -1;
        foreach ($events as $event) {
            if ($event->getId() <= $prev) {
                return false;
            }
            $prev = $event->getId();
        }
        return true;
    }

    private function applySchema(\PDO $pdo): void
    {
        $sql = file_get_contents(__DIR__ . '/../../sql/schema.sql');
        if ($sql === false) {
            throw new RuntimeException('Cannot read schema.sql');
        }
        $pdo->exec($sql);
    }

    private function truncateTables(\PDO $pdo): void
    {
        $pdo->exec('TRUNCATE TABLE event_inbox, events, source_cursor, source_rate_limit, event_request_log');
    }

    private function seedCursor(\PDO $pdo, string $sourceName, int $lastRequestedId, int $lastStoredId): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO source_cursor (source_name, last_requested_id, last_stored_id) VALUES (:source, :requested, :stored)',
        );
        $stmt->execute([
            'source' => $sourceName,
            'requested' => $lastRequestedId,
            'stored' => $lastStoredId,
        ]);
    }

    private function runLoaderOnce(
        EventSourceInterface $source,
        PdoSourceLeaseManager $leaseManager,
        PdoSourceRateLimiter $rateLimiter,
        PdoSourceCursorStore $cursorStore,
        PdoSourceRequestLog $requestLog,
        PdoEventInbox $inbox,
        PdoEventStorage $storage,
        StdoutLogger $logger,
    ): void {
        $stopController = new StopController();
        $stoppingSource = new StoppingEventSource($source, $stopController);

        $loader = new EventLoader(
            new class ($stoppingSource) implements \App\EventLoading\Contracts\SourceRegistryInterface {
                public function __construct(private EventSourceInterface $source)
                {
                }

                public function getSources(): iterable
                {
                    return [$this->source];
                }
            },
            $leaseManager,
            $rateLimiter,
            $cursorStore,
            $requestLog,
            $logger,
            new EventBatchHandler(
                new EventBatchValidator($logger),
                $inbox,
                $storage,
                $cursorStore,
                $requestLog,
                $logger,
            ),
        );

        $stopController->setLoader($loader);
        $loader->run();
    }
}
