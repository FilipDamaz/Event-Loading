<?php

declare(strict_types=1);

namespace App\EventLoading;

use App\EventLoading\Contracts\EventLoaderInterface;
use App\EventLoading\Contracts\EventSourceInterface;
use App\EventLoading\Contracts\LoggerInterface;
use App\EventLoading\Contracts\SourceCursorStoreInterface;
use App\EventLoading\Contracts\SourceLeaseInterface;
use App\EventLoading\Contracts\SourceLeaseManagerInterface;
use App\EventLoading\Contracts\SourceRateLimiterInterface;
use App\EventLoading\Contracts\SourceRegistryInterface;
use App\EventLoading\Contracts\SourceRequestLogInterface;
use App\EventLoading\Exception\EventSourceUnavailableException;
use App\EventLoading\Handler\EventBatchHandler;
use Throwable;

final class EventLoader implements EventLoaderInterface
{
    public const DEFAULT_BATCH_SIZE = 1000;
    public const DEFAULT_MIN_INTERVAL_MS = 200;
    public const DEFAULT_LEASE_TTL_MS = 2000;
    public const DEFAULT_IDLE_SLEEP_MS = 10;

    private bool $running = true;
    private int $batchSize;
    private int $minIntervalMs;
    private int $leaseTtlMs;
    private int $idleSleepMs;

    public function __construct(
        private SourceRegistryInterface $registry,
        private SourceLeaseManagerInterface $leaseManager,
        private SourceRateLimiterInterface $rateLimiter,
        private SourceCursorStoreInterface $cursorStore,
        private SourceRequestLogInterface $requestLog,
        private LoggerInterface $logger,
        private EventBatchHandler $batchHandler,
        int $batchSize = self::DEFAULT_BATCH_SIZE,
        int $minIntervalMs = self::DEFAULT_MIN_INTERVAL_MS,
        int $leaseTtlMs = self::DEFAULT_LEASE_TTL_MS,
        int $idleSleepMs = self::DEFAULT_IDLE_SLEEP_MS,
    ) {
        $this->batchSize = max(1, min(1000, $batchSize));
        $this->minIntervalMs = max(200, $minIntervalMs);
        $this->leaseTtlMs = max(500, $leaseTtlMs);
        $this->idleSleepMs = max(1, $idleSleepMs);
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function run(): void
    {
        while ($this->running) {
            $didWork = false;

            foreach ($this->registry->getSources() as $source) {
                $didWork = $this->processSource($source) || $didWork;
            }

            if (!$didWork) {
                usleep($this->idleSleepMs * 1000);
            }
        }
    }

    private function processSource(EventSourceInterface $source): bool
    {
        $sourceName = $source->getName();
        $lease = $this->leaseManager->acquire($sourceName, $this->leaseTtlMs);

        if ($lease === null) {
            return false;
        }

        $requestId = null;

        try {
            if (!$this->rateLimiter->tryAcquire($sourceName, $this->minIntervalMs)) {
                return false;
            }

            $afterId = $this->cursorStore->getLastRequestedId($sourceName);
            $requestId = $this->requestLog->reserve($sourceName, $afterId, $this->batchSize);
            if ($requestId === null) {
                return false;
            }

            $events = $source->fetchEvents($afterId, $this->batchSize);

            if ($events === []) {
                $this->requestLog->release($requestId);
                return false;
            }

            $this->batchHandler->handle($sourceName, $events, $afterId, $requestId);

            return true;
        } catch (EventSourceUnavailableException $e) {
            if ($requestId !== null) {
                $this->requestLog->markFailed($requestId, $e->getMessage());
            }
            $this->logger->warning('Event source unavailable, skipping.', [
                'source' => $sourceName,
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (Throwable $e) {
            if ($requestId !== null) {
                $this->requestLog->markFailed($requestId, $e->getMessage());
            }
            $this->logger->error('Unexpected error while loading events.', [
                'source' => $sourceName,
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            $this->releaseLease($lease);
        }
    }

    private function releaseLease(SourceLeaseInterface $lease): void
    {
        try {
            $lease->release();
        } catch (Throwable $e) {
            $this->logger->error('Failed to release source lease.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
