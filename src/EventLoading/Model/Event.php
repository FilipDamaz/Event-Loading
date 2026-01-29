<?php

declare(strict_types=1);

namespace App\EventLoading\Model;

final class Event implements EventInterface
{
    /** @var array<string, mixed> */
    private array $payload;

    /** @param array<string, mixed> $payload */
    public function __construct(private int $id, array $payload = [])
    {
        $this->payload = $payload;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
