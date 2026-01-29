<?php

declare(strict_types=1);

namespace App\EventLoading\Contracts;

interface EventLoaderInterface
{
    public function run(): void;
}
