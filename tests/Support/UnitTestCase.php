<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\EventLoading\Contracts\EventSourceInterface;
use App\EventLoading\Contracts\LoggerInterface;
use App\EventLoading\Contracts\SourceCursorStoreInterface;
use App\EventLoading\Contracts\SourceRegistryInterface;
use App\EventLoading\Contracts\SourceRequestLogInterface;
use App\EventLoading\EventLoader;
use App\EventLoading\Handler\EventBatchHandler;
use App\EventLoading\Validation\EventBatchValidator;
use PHPUnit\Framework\TestCase;

abstract class UnitTestCase extends TestCase
{
    protected function createLogger(): LoggerInterface
    {
        return new NullLogger();
    }

    protected function createRegistry(EventSourceInterface $source): SourceRegistryInterface
    {
        return new SingleSourceRegistry($source);
    }

    protected function createCursorStore(): InMemoryCursorStore
    {
        return new InMemoryCursorStore();
    }

    protected function createInbox(): InMemoryInbox
    {
        return new InMemoryInbox();
    }

    protected function createStorage(): InMemoryStorage
    {
        return new InMemoryStorage();
    }

    protected function createRequestLog(int $reserveId = 1): SourceRequestLogInterface
    {
        return new DummyRequestLog($reserveId);
    }

    protected function createValidator(LoggerInterface $logger): EventBatchValidator
    {
        return new EventBatchValidator($logger);
    }

    protected function createHandler(
        EventBatchValidator $validator,
        InMemoryInbox $inbox,
        InMemoryStorage $storage,
        SourceCursorStoreInterface $cursorStore,
        SourceRequestLogInterface $requestLog,
        LoggerInterface $logger,
    ): EventBatchHandler {
        return new EventBatchHandler($validator, $inbox, $storage, $cursorStore, $requestLog, $logger);
    }

    protected function createLoader(
        SourceRegistryInterface $registry,
        SourceCursorStoreInterface $cursorStore,
        SourceRequestLogInterface $requestLog,
        LoggerInterface $logger,
        EventBatchHandler $handler,
    ): EventLoader {
        return new EventLoader(
            $registry,
            new AllowAllLeaseManager(),
            new AllowAllRateLimiter(),
            $cursorStore,
            $requestLog,
            $logger,
            $handler,
        );
    }
}
