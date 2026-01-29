<?php

declare(strict_types=1);

namespace App\EventLoading\Contracts;

interface SourceLeaseInterface
{
    public function release(): void;
}
