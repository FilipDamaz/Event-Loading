<?php

declare(strict_types=1);

namespace App\EventLoading\Contracts;

interface SourceRegistryInterface
{
    /** @return iterable<EventSourceInterface> */
    public function getSources(): iterable;
}
