<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\EventLoading\Contracts\EventSourceInterface;
use App\EventLoading\Contracts\SourceRegistryInterface;

final class SingleSourceRegistry implements SourceRegistryInterface
{
    public function __construct(private EventSourceInterface $source)
    {
    }

    public function getSources(): iterable
    {
        return [$this->source];
    }
}
