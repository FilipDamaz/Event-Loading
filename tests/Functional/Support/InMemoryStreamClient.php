<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\EventLoading\Infrastructure\RabbitMq\StreamClientInterface;
use App\EventLoading\Infrastructure\RabbitMq\StreamMessage;

final class InMemoryStreamClient implements StreamClientInterface
{
    /** @var list<StreamMessage> */
    private array $messages = [];

    /** @param list<StreamMessage> $messages */
    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }

    public function read(string $streamName, int $offset, int $limit): array
    {
        $filtered = array_values(array_filter($this->messages, static function (StreamMessage $message) use ($offset) {
            return $message->getOffset() >= $offset;
        }));

        return array_slice($filtered, 0, $limit);
    }
}
