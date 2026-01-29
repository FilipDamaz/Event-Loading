<?php

declare(strict_types=1);

namespace App\EventLoading\Contracts;

use App\EventLoading\Model\EventInterface;

interface EventInboxInterface
{
    /** @param list<EventInterface> $events */
    public function storeInbox(string $sourceName, array $events): void;
}
