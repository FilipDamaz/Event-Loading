<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\EventLoading\InboxRetryWorker;

final class TestInboxRetryWorker extends InboxRetryWorker
{
    public int $runCount = 0;
    public int $runOnceCount = 0;

    public function __construct()
    {
    }

    public function run(): void
    {
        $this->runCount++;
    }

    public function runOnce(): bool
    {
        $this->runOnceCount++;
        return true;
    }
}
