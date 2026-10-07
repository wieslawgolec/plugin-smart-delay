<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use MauticPlugin\MauticSmartDelayBundle\Command\AnalyzeEngagementCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AnalyzeEngagementCommandTest extends TestCase
{
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }

    public function testDryRunSucceedsWithEmptyData(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('executeQuery')->willReturn($result);

        $command = new AnalyzeEngagementCommand($this->connection);
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Computed optimal hours for 0 contacts', $tester->getDisplay());
    }

    public function testDryRunComputesTopHourPerLead(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['lead_id' => 1, 'active_hour' => 9,  'cnt' => 10],
            ['lead_id' => 1, 'active_hour' => 14, 'cnt' => 3],
            ['lead_id' => 2, 'active_hour' => 20, 'cnt' => 7],
        ]);
        $this->connection->method('executeQuery')->willReturn($result);

        $command = new AnalyzeEngagementCommand($this->connection);
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Computed optimal hours for 2 contacts', $tester->getDisplay());
    }

    public function testQueryFailureReturnsFailure(): void
    {
        $this->connection
            ->method('executeQuery')
            ->willThrowException(new \RuntimeException('connection lost'));

        $command = new AnalyzeEngagementCommand($this->connection);
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to query audit_log', $tester->getDisplay());
    }

    public function testOptionsAreAccepted(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('executeQuery')->willReturn($result);

        $command = new AnalyzeEngagementCommand($this->connection);
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([
            '--days'       => '30',
            '--batch-size' => '100',
            '--dry-run'    => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Look-back: 30 days', $tester->getDisplay());
        $this->assertStringContainsString('Batch size: 100', $tester->getDisplay());
    }
}
