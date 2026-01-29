<?php

declare(strict_types=1);

namespace App\EventLoading\Contracts;

use App\EventLoading\Model\EventInterface;

interface EventStorageInterface
{
    /** @param list<EventInterface> $events */
    public function store(string $sourceName, array $events): void;
}
