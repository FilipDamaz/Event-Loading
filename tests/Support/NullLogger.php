<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\EventLoading\Contracts\LoggerInterface;

final class NullLogger implements LoggerInterface
{
    public function info(string $message, array $context = []): void
    {
    }

    public function warning(string $message, array $context = []): void
    {
    }

    public function error(string $message, array $context = []): void
    {
    }
}
