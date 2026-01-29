<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventLoading\Infrastructure\Pdo\PdoSourceRequestLog;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class PdoSourceRequestLogTest extends TestCase
{
    public function testReserveReturnsNullOnConflict(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())->method('prepare')->willReturn($stmt);
        $stmt->expects($this->once())->method('execute');
        $pdo->expects($this->once())->method('lastInsertId')->willReturn('0');

        $log = new PdoSourceRequestLog($pdo);
        $this->assertNull($log->reserve('source', 0, 100));
    }
}
