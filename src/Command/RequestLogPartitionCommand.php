<?php

declare(strict_types=1);

namespace App\Command;

use DateInterval;
use DateTimeImmutable;
use PDO;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:request-log-create-partitions',
    description: 'Create monthly partitions for event_request_log_archive.',
)]
final class RequestLogPartitionCommand extends Command
{
    public function __construct(private PDO $pdo)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('months-ahead', null, InputOption::VALUE_REQUIRED, 'How many months ahead to create', '3')
            ->addOption('start', null, InputOption::VALUE_REQUIRED, 'Start month (YYYY-MM), defaults to current month');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $monthsAhead = (int) $input->getOption('months-ahead');
        if ($monthsAhead < 1) {
            $output->writeln('<error>months-ahead must be >= 1</error>');
            return Command::INVALID;
        }

        $start = $input->getOption('start');
        $startDate = $this->resolveStartDate($start);

        $this->pdo->beginTransaction();

        try {
            $this->ensureArchiveTable();

            $current = $startDate;
            for ($i = 0; $i < $monthsAhead; $i++) {
                $this->createMonthlyPartition($current);
                $current = $current->add(new DateInterval('P1M'));
            }

            $this->pdo->commit();
            $output->writeln('<info>Archive partitions ensured.</info>');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $output->writeln('<error>Partition creation failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function resolveStartDate(?string $start): DateTimeImmutable
    {
        if ($start === null || $start === '') {
            return new DateTimeImmutable('first day of this month 00:00:00');
        }

        $date = DateTimeImmutable::createFromFormat('Y-m', $start);
        if ($date === false) {
            throw new \InvalidArgumentException('Invalid start format. Use YYYY-MM.');
        }

        return $date->setDate((int) $date->format('Y'), (int) $date->format('m'), 1)->setTime(0, 0, 0);
    }

    private function ensureArchiveTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS event_request_log_archive '
            . '(LIKE event_request_log INCLUDING ALL) '
            . 'PARTITION BY RANGE (created_at)',
        );
    }

    private function createMonthlyPartition(DateTimeImmutable $monthStart): void
    {
        $from = $monthStart->format('Y-m-01');
        $to = $monthStart->add(new DateInterval('P1M'))->format('Y-m-01');
        $table = sprintf('event_request_log_archive_%s', $monthStart->format('Y_m'));

        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS %s PARTITION OF event_request_log_archive '
            . "FOR VALUES FROM ('%s') TO ('%s')",
            $table,
            $from,
            $to,
        );
        $this->pdo->exec($sql);

        $indexSql = sprintf(
            'CREATE INDEX IF NOT EXISTS %s_status_idx ON %s (status, created_at)',
            $table,
            $table,
        );
        $this->pdo->exec($indexSql);
    }
}
