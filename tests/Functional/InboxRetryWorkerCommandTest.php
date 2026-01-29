<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Functional\Support\TestInboxRetryWorker;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InboxRetryWorkerCommandTest extends KernelTestCase
{
    public function testCommandRunsOnceWhenFlagProvided(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('app:inbox-retry-worker');
        $tester = new CommandTester($command);

        $tester->execute(['--once' => true]);

        $worker = self::getContainer()->get(TestInboxRetryWorker::class);
        $this->assertSame(0, $worker->runCount);
        $this->assertSame(1, $worker->runOnceCount);
    }

    public function testCommandRunsLoopWhenNoFlagProvided(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('app:inbox-retry-worker');
        $tester = new CommandTester($command);

        $tester->execute([]);

        $worker = self::getContainer()->get(TestInboxRetryWorker::class);
        $this->assertSame(1, $worker->runCount);
        $this->assertSame(0, $worker->runOnceCount);
    }
}
