<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Throwable;

class PhpAmqplibStreamClient implements StreamClientInterface
{
    public const DEFAULT_READ_TIMEOUT_MS = 500;
    public const OFFSET_FIRST = 'first';

    public function __construct(private string $dsn, private int $readTimeoutMs = self::DEFAULT_READ_TIMEOUT_MS)
    {
    }

    public function read(string $streamName, int $offset, int $limit): array
    {
        [$connection, $channel] = $this->createChannelAndConnection();

        /** @var Throwable|null $callbackError */
        $callbackError = null;
        try {
            $this->declareStream($channel, $streamName);

            $prefetch = max(1, min(1000, $limit));
            $channel->basic_qos(0, $prefetch, false);

            $messages = [];
            $consumerTag = '';

            $callback = function (AMQPMessage $message) use (&$messages, $channel, &$consumerTag, $limit, &$callbackError): void {
                if ($callbackError !== null) {
                    return;
                }

                try {
                    $offset = $this->extractOffset($message);
                    $messages[] = new StreamMessage($offset, $message->getBody());
                    $message->ack();

                    if (count($messages) >= $limit) {
                        $channel->basic_cancel($consumerTag);
                    }
                } catch (Throwable $e) {
                    $callbackError = $e;
                    try {
                        // Best effort: invalid payloads are permanent; reject without requeue.
                        $message->nack(false, false);
                    } catch (Throwable $nackError) {
                        // Ignore nack errors; original exception will be propagated.
                    }
                    $channel->basic_cancel($consumerTag);
                }
            };

            $streamOffset = $offset < 0 ? self::OFFSET_FIRST : $offset;
            $arguments = new AMQPTable(['x-stream-offset' => $streamOffset]);
            $consumerTag = $channel->basic_consume(
                $streamName,
                '',
                false,
                false,
                false,
                false,
                $callback,
                null,
                $arguments,
            );

            $deadline = microtime(true) + ($this->readTimeoutMs / 1000);

            while ($channel->is_consuming()) {
                $timeout = max(0.001, $deadline - microtime(true));
                try {
                    $channel->wait(null, false, $timeout);
                } catch (AMQPTimeoutException $e) {
                    break;
                }

                if (microtime(true) >= $deadline) {
                    break;
                }
            }

            $channel->basic_cancel($consumerTag);

            if ($callbackError !== null) {
                throw new RuntimeException('Stream callback failed.', 0, $callbackError);
            }

            return $messages;
        } finally {
            $channel->close();
            $connection->close();
        }
    }

    /**
     * @param object $channel
     */
    protected function declareStream(object $channel, string $streamName): void
    {
        $channel->queue_declare(
            $streamName,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(['x-queue-type' => 'stream']),
        );
    }

    private function extractOffset(AMQPMessage $message): int
    {
        $headers = $message->get('application_headers');
        if ($headers instanceof AMQPTable) {
            $data = $headers->getNativeData();
            if (isset($data['x-stream-offset']) && is_numeric($data['x-stream-offset'])) {
                return (int) $data['x-stream-offset'];
            }
        }

        throw new RuntimeException('Missing x-stream-offset header in stream message.');
    }

    private function createConnection(): AMQPStreamConnection
    {
        $parts = parse_url($this->dsn);
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
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to connect to RabbitMQ.', 0, $e);
        }
    }

    /**
     * @return array{0: AMQPStreamConnection, 1: AMQPChannel}
     */
    protected function createChannelAndConnection(): array
    {
        $connection = $this->createConnection();
        $channel = $connection->channel();

        return [$connection, $channel];
    }
}
