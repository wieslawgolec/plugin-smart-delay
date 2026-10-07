<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticSmartDelayBundle\Execution\SmartDelayExecutor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SmartDelayExecutorTest extends TestCase
{
    private Connection&MockObject $connection;
    private SmartDelayExecutor $executor;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->executor   = new SmartDelayExecutor($this->connection, '');
    }

    public function testResolveOptimalHourReturnsPeakHour(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')->willReturn([
            'active_hour'       => 14,
            'interaction_count' => 42,
        ]);

        $this->connection
            ->method('executeQuery')
            ->willReturn($result);

        $this->assertSame(14, $this->executor->resolveOptimalHour(123));
    }

    public function testResolveOptimalHourFallsBackWhenNoHistory(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')->willReturn(false);

        $this->connection
            ->method('executeQuery')
            ->willReturn($result);

        $this->assertSame(9, $this->executor->resolveOptimalHour(999));
    }

    public function testResolveOptimalHourFallsBackOnQueryException(): void
    {
        $this->connection
            ->method('executeQuery')
            ->willThrowException(new \RuntimeException('DB down'));

        $this->assertSame(9, $this->executor->resolveOptimalHour(1));
    }

    public function testResolveOptimalHourClampsInvalidHour(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')->willReturn([
            'active_hour'       => 99,
            'interaction_count' => 1,
        ]);

        $this->connection
            ->method('executeQuery')
            ->willReturn($result);

        $this->assertSame(9, $this->executor->resolveOptimalHour(5));
    }

    public function testComputeTargetExecutionSameDayWhenHourInFuture(): void
    {
        $now    = new \DateTimeImmutable('2026-10-07 08:00:00', new \DateTimeZone('UTC'));
        $target = $this->executor->computeTargetExecution(14, $now);

        $this->assertSame('2026-10-07 14:00:00', $target->format('Y-m-d H:i:s'));
    }

    public function testComputeTargetExecutionNextDayWhenHourPassed(): void
    {
        $now    = new \DateTimeImmutable('2026-10-07 15:00:00', new \DateTimeZone('UTC'));
        $target = $this->executor->computeTargetExecution(14, $now);

        $this->assertSame('2026-10-08 14:00:00', $target->format('Y-m-d H:i:s'));
    }

    public function testComputeTargetExecutionExactHourSchedulesTomorrow(): void
    {
        $now    = new \DateTimeImmutable('2026-10-07 14:00:00', new \DateTimeZone('UTC'));
        $target = $this->executor->computeTargetExecution(14, $now);

        $this->assertSame('2026-10-08 14:00:00', $target->format('Y-m-d H:i:s'));
    }

    public function testExecuteSmartDelayDefersAndSetsResult(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')->willReturn([
            'active_hour'       => 10,
            'interaction_count' => 5,
        ]);
        $this->connection->method('executeQuery')->willReturn($result);

        $lead  = new Lead(42);
        $event = new CampaignExecutionEvent();
        $event->setLead($lead);

        $this->executor->executeSmartDelay($event);

        $this->assertTrue($event->getResult());
        $this->assertInstanceOf(\DateTimeInterface::class, $event->getDeferredUntil());
        $this->assertSame(10, (int) $event->getDeferredUntil()->format('G'));
    }
}
