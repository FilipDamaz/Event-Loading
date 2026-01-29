<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\EventLoading\EventLoader;
use App\Tests\Functional\Support\StopController;
use App\Tests\Functional\Support\TestEventSource;
use App\Tests\Support\InMemoryCursorStore;
use App\Tests\Support\InMemoryInbox;
use App\Tests\Support\InMemoryStorage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EventLoaderFunctionalTest extends KernelTestCase
{
    public function testLoaderFetchesAndPersistsEventsViaServices(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $loader = $container->get(EventLoader::class);
        $stopController = $container->get(StopController::class);
        $stopController->setLoader($loader);

        $loader->run();

        $source = $container->get(TestEventSource::class);
        $cursorStore = $container->get(InMemoryCursorStore::class);
        $inbox = $container->get(InMemoryInbox::class);
        $storage = $container->get(InMemoryStorage::class);
        $this->assertSame(0, $source->getLastAfterId());
        $this->assertSame(2, $cursorStore->getLastRequestedId('test-source'));
        $this->assertSame(2, $cursorStore->getLastStoredId('test-source'));
        $this->assertCount(2, $inbox->getStored('test-source'));
        $this->assertCount(2, $storage->getStored('test-source'));
    }

    public function testLoaderSkipsUnavailableSource(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $loader = $container->get(EventLoader::class);
        $stopController = $container->get(StopController::class);
        $stopController->setLoader($loader);

        $source = $container->get(TestEventSource::class);
        $source->failOnNextFetch();

        $loader->run();

        $cursorStore = $container->get(InMemoryCursorStore::class);
        $inbox = $container->get(InMemoryInbox::class);
        $storage = $container->get(InMemoryStorage::class);

        $this->assertSame(0, $cursorStore->getLastRequestedId('test-source'));
        $this->assertSame(0, $cursorStore->getLastStoredId('test-source'));
        $this->assertCount(0, $inbox->getStored('test-source'));
        $this->assertCount(0, $storage->getStored('test-source'));
    }
}
