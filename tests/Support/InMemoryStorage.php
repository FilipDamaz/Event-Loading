<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\EventLoading\Contracts\EventStorageInterface;
use App\EventLoading\Model\EventInterface;

final class InMemoryStorage implements EventStorageInterface
{
    /** @var array<string, list<EventInterface>> */
    public array $stored = [];

    public function store(string $sourceName, array $events): void
    {
        $this->stored[$sourceName] = $events;
    }

    /**
     * @return list<EventInterface>
     */
    public function getStored(string $sourceName): array
    {
        return $this->stored[$sourceName] ?? [];
    }
}
