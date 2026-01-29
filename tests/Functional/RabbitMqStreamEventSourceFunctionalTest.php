<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\EventLoading\Infrastructure\RabbitMq\RabbitMqStreamEventSource;
use App\EventLoading\Infrastructure\RabbitMq\StreamMessage;
use App\Tests\Functional\Support\InMemoryStreamClient;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RabbitMqStreamEventSourceFunctionalTest extends KernelTestCase
{
    public function testFetchEventsUsesStreamClient(): void
    {
        self::bootKernel();

        $client = self::getContainer()->get(InMemoryStreamClient::class);
        $client->setMessages([
            new StreamMessage(5, '{"payload":{"foo":"bar"}}'),
            new StreamMessage(6, '{"payload":{"baz":"qux"}}'),
        ]);

        $source = self::getContainer()->get(RabbitMqStreamEventSource::class);
        $events = $source->fetchEvents(4, 10);

        $this->assertCount(2, $events);
        $this->assertSame(5, $events[0]->getId());
        $this->assertSame(['foo' => 'bar'], $events[0]->getPayload());
    }
}
