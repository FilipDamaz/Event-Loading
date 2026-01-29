<?php

declare(strict_types=1);

namespace App\EventLoading\Validation;

use App\EventLoading\Contracts\LoggerInterface;
use App\EventLoading\Model\EventInterface;
use RuntimeException;

final class EventBatchValidator
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * @param list<EventInterface> $events
     * @throws RuntimeException If events are not sorted or IDs are not monotonic
     */
    public function getMaxId(array $events, int $afterId, string $sourceName): int
    {
        $previousId = $afterId;
        $maxId = $afterId;

        foreach ($events as $event) {
            $currentId = $event->getId();

            if ($currentId <= $previousId) {
                $this->logger->error('Events are not sorted by ID in ascending order', [
                    'source' => $sourceName,
                    'previousId' => $previousId,
                    'currentId' => $currentId,
                ]);

                throw new RuntimeException(
                    sprintf(
                        'Events from source "%s" are not sorted by ID. Previous ID: %d, Current ID: %d',
                        $sourceName,
                        $previousId,
                        $currentId,
                    ),
                );
            }

            $maxId = max($maxId, $currentId);
            $previousId = $currentId;
        }

        return $maxId;
    }
}
