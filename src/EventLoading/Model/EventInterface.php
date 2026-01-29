<?php

declare(strict_types=1);

namespace App\EventLoading\Model;

interface EventInterface
{
    public function getId(): int;

    /** @return array<string, mixed> */
    public function getPayload(): array;
}
