<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventLoading\Model\Event;
use App\EventLoading\Validation\EventBatchValidator;
use App\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class EventBatchValidatorTest extends UnitTestCase
{
    /**
     * @param list<Event> $events
     */
    #[DataProvider('validBatchProvider')]
    public function testReturnsMaxIdForSortedEvents(array $events, int $afterId, int $expectedMaxId): void
    {
        $validator = new EventBatchValidator($this->createLogger());
        $maxId = $validator->getMaxId($events, $afterId, 'source-a');

        $this->assertSame($expectedMaxId, $maxId);
    }

    /**
     * @param list<Event> $events
     */
    #[DataProvider('invalidBatchProvider')]
    public function testThrowsForUnsortedEvents(array $events, int $afterId): void
    {
        $validator = new EventBatchValidator($this->createLogger());
        $this->expectException(RuntimeException::class);
        $validator->getMaxId($events, $afterId, 'source-a');
    }

    /**
     * @return array<string, array{0: list<Event>, 1: int, 2: int}>
     */
    public static function validBatchProvider(): array
    {
        return [
            'simple' => [[new Event(3), new Event(5), new Event(8)], 2, 8],
            'single' => [[new Event(1)], 0, 1],
            'gap' => [[new Event(10), new Event(20)], 5, 20],
        ];
    }

    /**
     * @return array<string, array{0: list<Event>, 1: int}>
     */
    public static function invalidBatchProvider(): array
    {
        return [
            'descending' => [[new Event(4), new Event(3)], 1],
            'duplicate' => [[new Event(5), new Event(5)], 1],
            'not-greater-than-after' => [[new Event(2)], 2],
        ];
    }
}
