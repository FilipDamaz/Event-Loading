<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventLoading\Infrastructure\Pdo\PdoSourceRateLimiter;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class PdoSourceRateLimiterTest extends TestCase
{
    public function testTryAcquireReturnsFalseWhenIntervalNotElapsed(): void
    {
        $pdo = $this->createMock(PDO::class);
        $selectStmt = $this->createMock(PDOStatement::class);
        $checkStmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('rollBack');
        $pdo->expects($this->never())->method('commit');

        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($selectStmt, $checkStmt);

        $selectStmt->expects($this->once())->method('execute')->with(['source' => 'source-a']);
        $selectStmt->expects($this->once())->method('fetch')->willReturn([
            'last_request_at' => '2026-01-29 00:00:00+00',
        ]);

        $checkStmt->expects($this->once())->method('execute')->with([
            'last_request_at' => '2026-01-29 00:00:00+00',
        ]);
        $checkStmt->expects($this->once())->method('fetchColumn')->willReturn('150');

        $limiter = new PdoSourceRateLimiter($pdo);
        $result = $limiter->tryAcquire('source-a', 200);

        $this->assertFalse($result);
    }

    public function testTryAcquireReturnsTrueWhenIntervalElapsed(): void
    {
        $pdo = $this->createMock(PDO::class);
        $selectStmt = $this->createMock(PDOStatement::class);
        $checkStmt = $this->createMock(PDOStatement::class);
        $updateStmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');
        $pdo->expects($this->never())->method('rollBack');

        $pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($selectStmt, $checkStmt, $updateStmt);

        $selectStmt->expects($this->once())->method('execute')->with(['source' => 'source-b']);
        $selectStmt->expects($this->once())->method('fetch')->willReturn([
            'last_request_at' => '2026-01-29 00:00:00+00',
        ]);

        $checkStmt->expects($this->once())->method('execute')->with([
            'last_request_at' => '2026-01-29 00:00:00+00',
        ]);
        $checkStmt->expects($this->once())->method('fetchColumn')->willReturn('250');

        $updateStmt->expects($this->once())->method('execute')->with(['source' => 'source-b']);

        $limiter = new PdoSourceRateLimiter($pdo);
        $result = $limiter->tryAcquire('source-b', 200);

        $this->assertTrue($result);
    }
}
