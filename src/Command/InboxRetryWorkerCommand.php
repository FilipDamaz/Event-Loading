<?php

declare(strict_types=1);

namespace App\Command;

use App\EventLoading\InboxRetryWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:inbox-retry-worker',
    description: 'Moves events from inbox to final storage.',
)]
final class InboxRetryWorkerCommand extends Command
{
    public function __construct(private InboxRetryWorker $worker)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Process a single batch and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('once')) {
            $this->worker->runOnce();
        } else {
            $this->worker->run();
        }

        return Command::SUCCESS;
    }
}
