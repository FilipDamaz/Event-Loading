<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventLoading\Handler\EventBatchHandler;
use App\EventLoading\Model\Event;
use App\EventLoading\Validation\EventBatchValidator;
use App\Tests\Support\UnitTestCase;
use RuntimeException;

final class EventBatchHandlerTest extends UnitTestCase
{
    public function testHandleMarksInboxOnlyThenSucceeded(): void
    {
        $logger = $this->createLogger();
        $validator = new EventBatchValidator($logger);
        $cursorStore = $this->createCursorStore();
        $inbox = $this->createInbox();
        $storage = $this->createStorage();

        $requestLog = new class () {
            /** @var list<array{string,int,int}> */
            public array $statuses = [];

            public function markInboxOnly(int $requestId, int $maxId): void
            {
                $this->statuses[] = ['inbox_only', $requestId, $maxId];
            }

            public function markSucceeded(int $requestId, int $maxId): void
            {
                $this->statuses[] = ['succeeded', $requestId, $maxId];
            }
        };

        $handler = new EventBatchHandler(
            $validator,
            $inbox,
            $storage,
            $cursorStore,
            new class ($requestLog) implements \App\EventLoading\Contracts\SourceRequestLogInterface {
                public function __construct(private object $inner)
                {
                }

                public function reserve(string $sourceName, int $afterId, int $limit): ?int
                {
                    return 1;
                }

                public function markSucceeded(int $requestId, int $maxId): void
                {
                    $this->inner->markSucceeded($requestId, $maxId);
                }

                public function markInboxOnly(int $requestId, int $maxId): void
                {
                    $this->inner->markInboxOnly($requestId, $maxId);
                }

                public function markFailed(int $requestId, string $error): void
                {
                }

                public function release(int $requestId): void
                {
                }
            },
            $logger,
        );

        $events = [new Event(10), new Event(11)];
        $handler->handle('source-a', $events, 0, 7);

        $this->assertSame(['inbox_only', 7, 11], $requestLog->statuses[0]);
        $this->assertSame(['succeeded', 7, 11], $requestLog->statuses[1]);
        $this->assertSame(11, $cursorStore->lastRequested['source-a'] ?? null);
        $this->assertSame(11, $cursorStore->lastStored['source-a'] ?? null);
    }

    public function testHandleDoesNotMarkSucceededWhenStorageFails(): void
    {
        $logger = $this->createLogger();
        $validator = new EventBatchValidator($logger);
        $cursorStore = $this->createCursorStore();
        $inbox = $this->createInbox();

        $storage = new class () implements \App\EventLoading\Contracts\EventStorageInterface {
            public function store(string $sourceName, array $events): void
            {
                throw new RuntimeException('store failed');
            }
        };

        $requestLog = new class () {
            /** @var list<array{string,int,int}> */
            public array $statuses = [];

            public function markInboxOnly(int $requestId, int $maxId): void
            {
                $this->statuses[] = ['inbox_only', $requestId, $maxId];
            }

            public function markSucceeded(int $requestId, int $maxId): void
            {
                $this->statuses[] = ['succeeded', $requestId, $maxId];
            }
        };

        $handler = new EventBatchHandler(
            $validator,
            $inbox,
            $storage,
            $cursorStore,
            new class ($requestLog) implements \App\EventLoading\Contracts\SourceRequestLogInterface {
                public function __construct(private object $inner)
                {
                }

                public function reserve(string $sourceName, int $afterId, int $limit): ?int
                {
                    return 1;
                }

                public function markSucceeded(int $requestId, int $maxId): void
                {
                    $this->inner->markSucceeded($requestId, $maxId);
                }

                public function markInboxOnly(int $requestId, int $maxId): void
                {
                    $this->inner->markInboxOnly($requestId, $maxId);
                }

                public function markFailed(int $requestId, string $error): void
                {
                }

                public function release(int $requestId): void
                {
                }
            },
            $logger,
        );

        $events = [new Event(10)];

        $this->expectException(RuntimeException::class);
        $handler->handle('source-b', $events, 0, 3);

        $this->assertSame(['inbox_only', 3, 10], $requestLog->statuses[0] ?? null);
        $this->assertNull($requestLog->statuses[1] ?? null);
    }
}
