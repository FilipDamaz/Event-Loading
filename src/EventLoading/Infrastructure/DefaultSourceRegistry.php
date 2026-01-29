<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure;

use App\EventLoading\Contracts\EventSourceInterface;
use App\EventLoading\Contracts\SourceRegistryInterface;

final class DefaultSourceRegistry implements SourceRegistryInterface
{
    /** @param iterable<EventSourceInterface> $sources */
    public function __construct(private iterable $sources)
    {
    }

    public function getSources(): iterable
    {
        return $this->sources;
    }
}
