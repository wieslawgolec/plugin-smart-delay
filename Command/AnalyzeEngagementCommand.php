<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Daily batch job that pre-computes optimal engagement hours per contact
 * and stores them for fast lookup during campaign execution.
 *
 * Schedule via cron:
 *   0 3 * * * php bin/console mautic:smartdelay:analyze --no-interaction
 */
#[AsCommand(
    name: 'mautic:smartdelay:analyze',
    description: 'Pre-compute optimal send hours from historical engagement data',
)]
class AnalyzeEngagementCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Look-back window in days', 90)
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Contacts per batch', 500)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compute but do not write results');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $days      = max(1, (int) $input->getOption('days'));
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $dryRun    = (bool) $input->getOption('dry-run');

        $prefix = defined('MAUTIC_TABLE_PREFIX') ? MAUTIC_TABLE_PREFIX : '';
        $table  = $prefix.'audit_log';

        $io->title('Smart Delay – Engagement Analysis');
        $io->text(sprintf('Look-back: %d days | Batch size: %d | Dry-run: %s', $days, $batchSize, $dryRun ? 'yes' : 'no'));

        $sql = sprintf(
            'SELECT lead_id, HOUR(date_added) AS active_hour, COUNT(*) AS cnt
             FROM %s
             WHERE lead_id IS NOT NULL
               AND bundle IN (\'email\', \'page\')
               AND date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY lead_id, active_hour
             ORDER BY lead_id, cnt DESC',
            $table
        );

        try {
            $rows = $this->connection->executeQuery($sql, ['days' => $days])->fetchAllAssociative();
        } catch (\Throwable $e) {
            $io->error('Failed to query audit_log: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Keep only the top hour per lead
        $optimal = [];
        foreach ($rows as $row) {
            $leadId = (int) $row['lead_id'];
            if (!isset($optimal[$leadId])) {
                $optimal[$leadId] = (int) $row['active_hour'];
            }
        }

        $total = count($optimal);
        $io->success(sprintf('Computed optimal hours for %d contacts.', $total));

        if ($dryRun || $total === 0) {
            return Command::SUCCESS;
        }

        // Persist into a lightweight key-value style table if it exists;
        // otherwise write a summary only (plugin remains functional via live query).
        $summaryTable = $prefix.'smartdelay_optimal_hours';
        $schemaExists = false;

        try {
            $this->connection->executeQuery('SELECT 1 FROM '.$summaryTable.' LIMIT 1');
            $schemaExists = true;
        } catch (\Throwable) {
            $schemaExists = false;
        }

        if (!$schemaExists) {
            $io->note(sprintf(
                'Table %s not found – results were computed but not persisted. Create the table or rely on live queries.',
                $summaryTable
            ));

            return Command::SUCCESS;
        }

        $written = 0;
        $chunks  = array_chunk($optimal, $batchSize, true);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $leadId => $hour) {
                $this->connection->executeStatement(
                    sprintf(
                        'INSERT INTO %s (lead_id, optimal_hour, updated_at)
                         VALUES (:leadId, :hour, NOW())
                         ON DUPLICATE KEY UPDATE optimal_hour = VALUES(optimal_hour), updated_at = NOW()',
                        $summaryTable
                    ),
                    ['leadId' => $leadId, 'hour' => $hour]
                );
                ++$written;
            }
        }

        $io->success(sprintf('Persisted optimal hours for %d contacts.', $written));

        return Command::SUCCESS;
    }
}
