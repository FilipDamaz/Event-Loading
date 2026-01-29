<?php

declare(strict_types=1);

namespace App\Command;

use PDO;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:request-log-prune',
    description: 'Prune old request log entries with optional archiving.',
)]
final class RequestLogPruneCommand extends Command
{
    public function __construct(private PDO $pdo)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('succeeded-days', null, InputOption::VALUE_REQUIRED, 'Retention for succeeded entries', '7')
            ->addOption('failed-days', null, InputOption::VALUE_REQUIRED, 'Retention for failed entries', '30')
            ->addOption('archive', null, InputOption::VALUE_NONE, 'Archive rows to event_request_log_archive if present');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $succeededDays = (int) $input->getOption('succeeded-days');
        $failedDays = (int) $input->getOption('failed-days');
        $archive = (bool) $input->getOption('archive');

        if ($succeededDays < 0 || $failedDays < 0) {
            $output->writeln('<error>Retention days must be non-negative.</error>');
            return Command::INVALID;
        }

        $this->pdo->beginTransaction();

        try {
            $hasArchive = false;
            if ($archive) {
                $stmt = $this->pdo->query("SELECT to_regclass('public.event_request_log_archive')");
                $hasArchive = ($stmt !== false && $stmt->fetchColumn() !== null);
            }

            if ($hasArchive) {
                $this->archiveAndDelete('succeeded', $succeededDays);
                $this->archiveAndDelete('failed', $failedDays);
            } else {
                $this->deleteOnly('succeeded', $succeededDays);
                $this->deleteOnly('failed', $failedDays);
            }

            $this->pdo->commit();

            $output->writeln('<info>Request log prune completed.</info>');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $output->writeln('<error>Request log prune failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function archiveAndDelete(string $status, int $days): void
    {
        $interval = sprintf('%d days', $days);
        $insert = $this->pdo->prepare(
            'INSERT INTO event_request_log_archive SELECT * FROM event_request_log '
            . 'WHERE status = :status AND created_at < (NOW() - :interval::interval)',
        );
        $insert->execute([
            'status' => $status,
            'interval' => $interval,
        ]);

        $this->deleteOnly($status, $days);
    }

    private function deleteOnly(string $status, int $days): void
    {
        $interval = sprintf('%d days', $days);
        $delete = $this->pdo->prepare(
            'DELETE FROM event_request_log WHERE status = :status AND created_at < (NOW() - :interval::interval)',
        );
        $delete->execute([
            'status' => $status,
            'interval' => $interval,
        ]);
    }
}
