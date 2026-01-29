<?php

declare(strict_types=1);

namespace App\EventLoading\Contracts;

use App\EventLoading\Exception\EventSourceUnavailableException;
use App\EventLoading\Model\EventInterface;

interface EventSourceInterface
{
    public function getName(): string;

    /**
     * @param int $afterId Last known event id (exclusive).
     * @param int $limit Maximum number of events to return (<= 1000).
     * @return list<EventInterface>
     * @throws EventSourceUnavailableException
     */
    public function fetchEvents(int $afterId, int $limit): array;
}
