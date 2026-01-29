<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\EventLoading\Contracts\EventSourceInterface;
use App\EventLoading\Model\Event;

final class TestEventSource implements EventSourceInterface
{
    private int $lastAfterId = 0;
    private bool $failOnce = false;

    public function __construct(private StopController $stopController)
    {
    }

    public function getName(): string
    {
        return 'test-source';
    }

    public function fetchEvents(int $afterId, int $limit): array
    {
        $this->lastAfterId = $afterId;
        $this->stopController->stop();

        if ($this->failOnce) {
            $this->failOnce = false;
            throw new \App\EventLoading\Exception\EventSourceUnavailableException('Simulated failure');
        }

        return [new Event($afterId + 1), new Event($afterId + 2)];
    }

    public function getLastAfterId(): int
    {
        return $this->lastAfterId;
    }

    public function failOnNextFetch(): void
    {
        $this->failOnce = true;
    }
}
