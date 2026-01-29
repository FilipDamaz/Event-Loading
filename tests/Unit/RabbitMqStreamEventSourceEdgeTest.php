<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventLoading\Exception\EventSourceUnavailableException;
use App\EventLoading\Infrastructure\RabbitMq\RabbitMqStreamEventSource;
use App\EventLoading\Infrastructure\RabbitMq\StreamClientInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RabbitMqStreamEventSourceEdgeTest extends TestCase
{
    public function testEmptyStreamReturnsEmptyArray(): void
    {
        $client = new class () implements StreamClientInterface {
            public function read(string $streamName, int $offset, int $limit): array
            {
                return [];
            }
        };

        $source = new RabbitMqStreamEventSource($client, 'stream-empty', 'source-empty');
        $events = $source->fetchEvents(0, 10);

        $this->assertSame([], $events);
    }

    public function testNetworkErrorIsWrapped(): void
    {
        $client = new class () implements StreamClientInterface {
            public function read(string $streamName, int $offset, int $limit): array
            {
                throw new RuntimeException('network down');
            }
        };

        $source = new RabbitMqStreamEventSource($client, 'stream-net', 'source-net');

        $this->expectException(EventSourceUnavailableException::class);
        $source->fetchEvents(0, 10);
    }
}
