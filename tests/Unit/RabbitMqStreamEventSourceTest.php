<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventLoading\Infrastructure\RabbitMq\RabbitMqStreamEventSource;
use App\EventLoading\Infrastructure\RabbitMq\StreamClientInterface;
use App\EventLoading\Infrastructure\RabbitMq\StreamMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RabbitMqStreamEventSourceTest extends TestCase
{
    public function testFetchEventsMapsOffsetsAndPayload(): void
    {
        $client = new class () implements StreamClientInterface {
            public function read(string $streamName, int $offset, int $limit): array
            {
                return [
                    new StreamMessage(10, '{"payload":{"foo":"bar"}}'),
                    new StreamMessage(11, '{"value":123}'),
                ];
            }
        };

        $source = new RabbitMqStreamEventSource($client, 'stream-a', 'source-a');
        $events = $source->fetchEvents(9, 1000);

        $this->assertCount(2, $events);
        $this->assertSame(10, $events[0]->getId());
        $this->assertSame(['foo' => 'bar'], $events[0]->getPayload());
        $this->assertSame(11, $events[1]->getId());
        $this->assertSame(['value' => 123], $events[1]->getPayload());
    }

    public function testFetchEventsPassesAfterIdAsOffset(): void
    {
        $client = new class () implements StreamClientInterface {
            /** @var array{string,int,int}|null */
            public ?array $captured = null;

            public function read(string $streamName, int $offset, int $limit): array
            {
                $this->captured = [$streamName, $offset, $limit];
                return [];
            }
        };

        $source = new RabbitMqStreamEventSource($client, 'stream-b', 'source-b');
        $source->fetchEvents(41, 123);

        $this->assertSame(['stream-b', 42, 123], $client->captured);
    }

    public function testInvalidJsonThrowsRuntimeException(): void
    {
        $client = new class () implements StreamClientInterface {
            public function read(string $streamName, int $offset, int $limit): array
            {
                return [new StreamMessage(1, 'not-json')];
            }
        };

        $source = new RabbitMqStreamEventSource($client, 'stream-c', 'source-c');

        $this->expectException(RuntimeException::class);
        $source->fetchEvents(0, 10);
    }

    public function testNonObjectPayloadThrowsRuntimeException(): void
    {
        $client = new class () implements StreamClientInterface {
            public function read(string $streamName, int $offset, int $limit): array
            {
                return [new StreamMessage(1, '"scalar"')];
            }
        };

        $source = new RabbitMqStreamEventSource($client, 'stream-d', 'source-d');

        $this->expectException(RuntimeException::class);
        $source->fetchEvents(0, 10);
    }
}
