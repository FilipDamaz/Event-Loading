<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventLoading\Contracts\EventSourceInterface;
use App\EventLoading\Contracts\SourceRequestLogInterface;
use App\EventLoading\Exception\EventSourceUnavailableException;
use App\EventLoading\Handler\EventBatchHandler;
use App\EventLoading\Model\Event;
use App\EventLoading\Validation\EventBatchValidator;
use App\Tests\Support\UnitTestCase;

final class EventLoaderRequestLogTest extends UnitTestCase
{
    public function testRequestLogReservationPreventsDuplicateFetch(): void
    {
        $events = [new Event(1)];
        $fetchCount = 0;

        $source = new class ($events, $fetchCount) implements EventSourceInterface {
            /** @var list<Event> */
            private array $events;
            private int $fetchCount;

            /** @param list<Event> $events */
            public function __construct(array $events, int &$fetchCount)
            {
                $this->events = $events;
                $this->fetchCount = &$fetchCount;
            }

            public function getName(): string
            {
                return 'source-rl';
            }

            public function fetchEvents(int $afterId, int $limit): array
            {
                $this->fetchCount++;
                return $this->events;
            }
        };

        $registry = $this->createRegistry($source);
        $cursorStore = $this->createCursorStore();

        $stopper = null;

        $requestLog = new class ($stopper) implements SourceRequestLogInterface {
            private bool $reserved = false;
            /** @var callable|null */
            private $stopper;

            public function __construct(?callable &$stopper)
            {
                $this->stopper = &$stopper;
            }

            public function reserve(string $sourceName, int $afterId, int $limit): ?int
            {
                if ($this->reserved) {
                    if ($this->stopper !== null) {
                        ($this->stopper)();
                    }
                    return null;
                }
                $this->reserved = true;
                return 1;
            }

            public function markSucceeded(int $requestId, int $maxId): void
            {
            }

            public function markInboxOnly(int $requestId, int $maxId): void
            {
            }

            public function markFailed(int $requestId, string $error): void
            {
            }

            public function release(int $requestId): void
            {
            }
        };

        $inbox = $this->createInbox();
        $storage = $this->createStorage();
        $logger = $this->createLogger();
        $validator = new EventBatchValidator($logger);
        $handler = new EventBatchHandler($validator, $inbox, $storage, $cursorStore, $requestLog, $logger);
        $loader = $this->createLoader($registry, $cursorStore, $requestLog, $logger, $handler);

        $stopper = static function () use ($loader): void {
            $loader->stop();
        };
        $loader->run();

        $validator2 = new EventBatchValidator($logger);
        $handler2 = new EventBatchHandler($validator2, $inbox, $storage, $cursorStore, $requestLog, $logger);
        $loader2 = $this->createLoader($registry, $cursorStore, $requestLog, $logger, $handler2);
        $stopper = static function () use ($loader2): void {
            $loader2->stop();
        };
        $loader2->run();

        $this->assertSame(1, $fetchCount);
    }

    public function testFailedRequestIsMarked(): void
    {
        $source = new class () implements EventSourceInterface {
            public function getName(): string
            {
                return 'source-fail';
            }

            public function fetchEvents(int $afterId, int $limit): array
            {
                throw new EventSourceUnavailableException('down');
            }
        };

        $registry = $this->createRegistry($source);
        $cursorStore = $this->createCursorStore();

        $stopper = null;

        $requestLog = new class ($stopper) implements SourceRequestLogInterface {
            public int $failedCount = 0;
            /** @var callable|null */
            private $stopper;

            public function __construct(?callable &$stopper)
            {
                $this->stopper = &$stopper;
            }

            public function reserve(string $sourceName, int $afterId, int $limit): ?int
            {
                return 1;
            }

            public function markSucceeded(int $requestId, int $maxId): void
            {
            }

            public function markInboxOnly(int $requestId, int $maxId): void
            {
            }

            public function markFailed(int $requestId, string $error): void
            {
                $this->failedCount++;
                if ($this->stopper !== null) {
                    ($this->stopper)();
                }
            }

            public function release(int $requestId): void
            {
            }
        };

        $inbox = $this->createInbox();
        $storage = $this->createStorage();
        $logger = $this->createLogger();
        $validator = new EventBatchValidator($logger);
        $handler = new EventBatchHandler($validator, $inbox, $storage, $cursorStore, $requestLog, $logger);
        $loader = $this->createLoader($registry, $cursorStore, $requestLog, $logger, $handler);

        $stopper = static function () use ($loader): void {
            $loader->stop();
        };
        $loader->run();

        $this->assertSame(1, $requestLog->failedCount);
    }
}
