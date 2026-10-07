<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\Execution;

use Doctrine\DBAL\Connection;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;

/**
 * Core engine that calculates the optimal send hour for a contact
 * based on historical engagement and defers campaign execution.
 */
class SmartDelayExecutor
{
    private const DEFAULT_OPTIMAL_HOUR = 9;

    private const TABLE_PREFIX_FALLBACK = '';

    public function __construct(
        private readonly Connection $connection,
        private readonly ?string $tablePrefix = null,
    ) {
    }

    public function executeSmartDelay(CampaignExecutionEvent $event): void
    {
        $lead   = $event->getLead();
        $leadId = $lead->getId();

        $optimalHour = $this->resolveOptimalHour((int) $leadId);

        $now             = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $targetExecution = $now->setTime($optimalHour, 0, 0);

        if ($targetExecution <= $now) {
            $targetExecution = $targetExecution->modify('+1 day');
        }

        $event->deferExecution($targetExecution);
        $event->setResult(true);
    }

    /**
     * Returns the hour (0-23) with the highest historical interaction count.
     * Falls back to DEFAULT_OPTIMAL_HOUR when no history exists.
     */
    public function resolveOptimalHour(int $leadId): int
    {
        $prefix = $this->tablePrefix ?? (defined('MAUTIC_TABLE_PREFIX') ? MAUTIC_TABLE_PREFIX : self::TABLE_PREFIX_FALLBACK);
        $table  = $prefix.'audit_log';

        $sql = sprintf(
            'SELECT HOUR(date_added) AS active_hour, COUNT(*) AS interaction_count
             FROM %s
             WHERE lead_id = :leadId AND bundle IN (\'email\', \'page\')
             GROUP BY active_hour
             ORDER BY interaction_count DESC
             LIMIT 1',
            $table
        );

        try {
            $result = $this->connection->executeQuery($sql, ['leadId' => $leadId])->fetchAssociative();
        } catch (\Throwable) {
            return self::DEFAULT_OPTIMAL_HOUR;
        }

        if (!$result || !isset($result['active_hour'])) {
            return self::DEFAULT_OPTIMAL_HOUR;
        }

        $hour = (int) $result['active_hour'];

        return ($hour >= 0 && $hour <= 23) ? $hour : self::DEFAULT_OPTIMAL_HOUR;
    }

    /**
     * Compute the next DateTimeImmutable at the given hour in UTC.
     * Useful for unit tests and the batch analysis command.
     */
    public function computeTargetExecution(int $optimalHour, ?\DateTimeImmutable $now = null): \DateTimeImmutable
    {
        $now             = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $targetExecution = $now->setTime($optimalHour, 0, 0);

        if ($targetExecution <= $now) {
            $targetExecution = $targetExecution->modify('+1 day');
        }

        return $targetExecution;
    }
}
