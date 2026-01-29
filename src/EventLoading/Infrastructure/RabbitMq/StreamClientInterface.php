<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure\RabbitMq;

interface StreamClientInterface
{
    /**
     * @return list<StreamMessage>
     */
    public function read(string $streamName, int $offset, int $limit): array;
}
