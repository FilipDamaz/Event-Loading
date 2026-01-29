<?php

declare(strict_types=1);

namespace App\EventLoading\Infrastructure;

use App\EventLoading\Contracts\LoggerInterface;

final class StdoutLogger implements LoggerInterface
{
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function log(string $level, string $message, array $context): void
    {
        $line = sprintf('[%s] %s %s', $level, $message, $context === [] ? '' : json_encode($context));
        error_log(rtrim($line));
    }
}
