<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure\RabbitMq;

use App\EventLoading\Contracts\EventSourceInterface;
use App\EventLoading\Exception\EventSourceUnavailableException;
use App\EventLoading\Model\Event;
use JsonException;
use RuntimeException;

final class RabbitMqStreamEventSource implements EventSourceInterface
{
    public function __construct(
        private StreamClientInterface $streamClient,
        private string $streamName,
        private string $sourceName,
    ) {
    }

    public function getName(): string
    {
        return $this->sourceName;
    }

    public function fetchEvents(int $afterId, int $limit): array
    {
        $offset = $afterId < 0 ? -1 : $afterId + 1;

        try {
            $messages = $this->streamClient->read($this->streamName, $offset, $limit);
        } catch (\Throwable $e) {
            throw new EventSourceUnavailableException('Stream read failed', 0, $e);
        }

        $events = [];

        foreach ($messages as $message) {
            $data = $this->decodeMessage($message->getData());
            $payload = $data['payload'] ?? $data;

            if (!is_array($payload)) {
                throw new RuntimeException('Stream message payload must be a JSON object.');
            }

            $events[] = new Event($message->getOffset(), $payload);
        }

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMessage(string $data): array
    {
        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid JSON payload in stream message.', 0, $e);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Stream message payload must be a JSON object.');
        }

        return $decoded;
    }
}
