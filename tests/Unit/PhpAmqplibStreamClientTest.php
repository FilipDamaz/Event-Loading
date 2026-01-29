<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventLoading\Infrastructure\RabbitMq\PhpAmqplibStreamClient;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PhpAmqplibStreamClientTest extends TestCase
{
    public function testCallbackErrorIsPropagated(): void
    {
        $channel = new class () {
            public bool $consuming = false;
            /** @var callable|null */
            public $callback = null;

            public function queue_declare(mixed ...$args): void
            {
            }

            public function basic_qos(mixed ...$args): void
            {
            }

            public function basic_consume(
                string $queue,
                string $consumerTag,
                bool $noLocal,
                bool $noAck,
                bool $exclusive,
                bool $noWait,
                callable $callback,
                mixed $ticket = null,
                mixed $arguments = null,
            ): string {
                $this->callback = $callback;
                $this->consuming = true;
                return 'tag';
            }

            public function is_consuming(): bool
            {
                return $this->consuming;
            }

            /** @param array<int, string>|null $allowedMethods */
            public function wait(?array $allowedMethods = null, bool $nonBlocking = false, float $timeout = 0.0): void
            {
                if ($this->callback !== null) {
                    $message = new AMQPMessage('{"payload":{}}');
                    ($this->callback)($message);
                    $this->consuming = false;
                    return;
                }

                throw new AMQPTimeoutException('timeout');
            }

            public function basic_cancel(string $consumerTag): void
            {
                $this->consuming = false;
            }

            public function close(): void
            {
            }
        };

        $connection = new class ($channel) {
            public function __construct(private object $channel)
            {
            }

            public function channel(): object
            {
                return $this->channel;
            }

            public function close(): void
            {
            }
        };

        $client = new class ($connection, $channel) extends PhpAmqplibStreamClient {
            public function __construct(private object $connection, private object $channel)
            {
                parent::__construct('amqp://guest:guest@localhost');
            }

            protected function createChannelAndConnection(): array
            {
                return [$this->connection, $this->channel];
            }
        };

        $this->expectException(RuntimeException::class);
        $client->read('stream', 0, 10);
    }
}
