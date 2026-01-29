<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure\RabbitMq;

final class StreamMessage
{
    public function __construct(private int $offset, private string $data)
    {
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getData(): string
    {
        return $this->data;
    }
}
