<?php

declare(strict_types=1);

namespace App\EventLoading\Handler;

use App\EventLoading\Contracts\EventInboxInterface;
use App\EventLoading\Contracts\EventStorageInterface;
use App\EventLoading\Contracts\LoggerInterface;
use App\EventLoading\Contracts\SourceCursorStoreInterface;
use App\EventLoading\Contracts\SourceRequestLogInterface;
use App\EventLoading\Model\EventInterface;
use App\EventLoading\Validation\EventBatchValidator;

final class EventBatchHandler
{
    public function __construct(
        private EventBatchValidator $validator,
        private EventInboxInterface $inbox,
        private EventStorageInterface $storage,
        private SourceCursorStoreInterface $cursorStore,
        private SourceRequestLogInterface $requestLog,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<EventInterface> $events
     */
    public function handle(string $sourceName, array $events, int $afterId, int $requestId): int
    {
        $maxId = $this->validator->getMaxId($events, $afterId, $sourceName);

        // Store in inbox for durability and to avoid re-fetching on retries.
        $this->inbox->storeInbox($sourceName, $events);

        // Only advance request cursor after inbox is persisted.
        $this->cursorStore->advanceLastRequestedId($sourceName, $maxId);
        $this->requestLog->markInboxOnly($requestId, $maxId);

        $this->storage->store($sourceName, $events);
        $this->cursorStore->advanceLastStoredId($sourceName, $maxId);
        $this->requestLog->markSucceeded($requestId, $maxId);

        $this->logger->info('Successfully loaded events', [
            'source' => $sourceName,
            'count' => count($events),
            'lastId' => $maxId,
        ]);

        return $maxId;
    }
}
