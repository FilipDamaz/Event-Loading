<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventLoading\Contracts\EventSourceInterface;
use App\EventLoading\Model\Event;
use App\Tests\Support\UnitTestCase;

final class EventLoaderTest extends UnitTestCase
{
    public function testLoaderStoresEventsAndAdvancesCursors(): void
    {
        $events = [new Event(10), new Event(11)];
        $stopper = null;

        $source = new class ($events, $stopper) implements EventSourceInterface {
            /** @var list<Event> */
            private array $events;
            public ?int $afterId = null;
            public ?int $limit = null;
            /** @var callable|null */
            private $stopper;

            /** @param list<Event> $events */
            public function __construct(array $events, ?callable &$stopper)
            {
                $this->events = $events;
                $this->stopper = &$stopper;
            }

            public function getName(): string
            {
                return 'source-a';
            }

            public function fetchEvents(int $afterId, int $limit): array
            {
                $this->afterId = $afterId;
                $this->limit = $limit;

                if ($this->stopper !== null) {
                    ($this->stopper)();
                }

                return $this->events;
            }
        };

        $registry = $this->createRegistry($source);
        $cursorStore = $this->createCursorStore();
        $storage = $this->createStorage();
        $inbox = $this->createInbox();
        $requestLog = $this->createRequestLog();
        $logger = $this->createLogger();
        $validator = $this->createValidator($logger);
        $handler = $this->createHandler($validator, $inbox, $storage, $cursorStore, $requestLog, $logger);
        $loader = $this->createLoader($registry, $cursorStore, $requestLog, $logger, $handler);

        $stopper = static function () use ($loader): void {
            $loader->stop();
        };

        $loader->run();

        $this->assertSame(0, $source->afterId);
        $this->assertSame(1000, $source->limit);
        $this->assertSame(11, $cursorStore->lastRequested['source-a'] ?? null);
        $this->assertSame(11, $cursorStore->lastStored['source-a'] ?? null);
        $this->assertCount(2, $inbox->stored['source-a'] ?? []);
        $this->assertCount(2, $storage->stored['source-a'] ?? []);
    }

    public function testLoaderUsesLastRequestedIdWhenFetching(): void
    {
        $events = [new Event(6)];
        $stopper = null;

        $source = new class ($events, $stopper) implements EventSourceInterface {
            /** @var list<Event> */
            private array $events;
            public ?int $afterId = null;
            /** @var callable|null */
            private $stopper;

            /** @param list<Event> $events */
            public function __construct(array $events, ?callable &$stopper)
            {
                $this->events = $events;
                $this->stopper = &$stopper;
            }

            public function getName(): string
            {
                return 'source-b';
            }

            public function fetchEvents(int $afterId, int $limit): array
            {
                $this->afterId = $afterId;

                if ($this->stopper !== null) {
                    ($this->stopper)();
                }

                return $this->events;
            }
        };

        $registry = $this->createRegistry($source);
        $cursorStore = $this->createCursorStore();
        $cursorStore->lastRequested['source-b'] = 5;
        $storage = $this->createStorage();
        $inbox = $this->createInbox();
        $requestLog = $this->createRequestLog();
        $logger = $this->createLogger();
        $validator = $this->createValidator($logger);
        $handler = $this->createHandler($validator, $inbox, $storage, $cursorStore, $requestLog, $logger);
        $loader = $this->createLoader($registry, $cursorStore, $requestLog, $logger, $handler);

        $stopper = static function () use ($loader): void {
            $loader->stop();
        };
        $loader->run();

        $this->assertSame(5, $source->afterId);
    }

    public function testLoaderRejectsUnsortedEvents(): void
    {
        $events = [new Event(10), new Event(9)];
        $stopper = null;

        $source = new class ($events, $stopper) implements EventSourceInterface {
            /** @var list<Event> */
            private array $events;
            /** @var callable|null */
            private $stopper;

            /** @param list<Event> $events */
            public function __construct(array $events, ?callable &$stopper)
            {
                $this->events = $events;
                $this->stopper = &$stopper;
            }

            public function getName(): string
            {
                return 'source-c';
            }

            public function fetchEvents(int $afterId, int $limit): array
            {
                if ($this->stopper !== null) {
                    ($this->stopper)();
                }

                return $this->events;
            }
        };

        $registry = $this->createRegistry($source);
        $cursorStore = $this->createCursorStore();
        $storage = $this->createStorage();
        $inbox = $this->createInbox();
        $requestLog = $this->createRequestLog();
        $logger = $this->createLogger();
        $validator = $this->createValidator($logger);
        $handler = $this->createHandler($validator, $inbox, $storage, $cursorStore, $requestLog, $logger);
        $loader = $this->createLoader($registry, $cursorStore, $requestLog, $logger, $handler);

        $stopper = static function () use ($loader): void {
            $loader->stop();
        };
        $loader->run();

        $this->assertSame(0, $cursorStore->lastRequested['source-c'] ?? 0);
        $this->assertSame(0, $cursorStore->lastStored['source-c'] ?? 0);
    }
}
