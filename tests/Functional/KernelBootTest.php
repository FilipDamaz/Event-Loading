<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class KernelBootTest extends KernelTestCase
{
    public function testKernelBoots(): void
    {
        self::bootKernel();

        $this->assertSame('test', self::$kernel->getEnvironment());
        $this->assertTrue(self::getContainer()->has('kernel'));
    }
}
