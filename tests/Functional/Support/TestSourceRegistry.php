<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\EventLoading\Contracts\SourceRegistryInterface;

final class TestSourceRegistry implements SourceRegistryInterface
{
    public function __construct(private TestEventSource $source)
    {
    }

    public function getSources(): iterable
    {
        return [$this->source];
    }
}
