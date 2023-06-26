<?php

declare(strict_types=1);

namespace SEEC\Behat\Tests\Services;

use PHPUnit\Framework\TestCase;
use SEEC\Behat\Context\Services\ProcessFactory;
use SEEC\Behat\Context\Services\ProcessFactoryInterface;
use Symfony\Component\Process\PhpExecutableFinder;

if (defined('BEHAT_BIN_PATH') === false) {
    define('BEHAT_BIN_PATH', 'vendor/bin/behat');
}

final class ProcessFactoryTest extends TestCase
{
    /** @var ProcessFactoryInterface */
    private $processFactory;

    public function setUp(): void
    {
        $phpFinder = $this->createMock(PhpExecutableFinder::class);
        $phpFinder->expects($this->once())
            ->method('find')
            ->willReturn('/usr/bin/php');

        $this->processFactory = new ProcessFactory($phpFinder);
    }

    public function test_it_can_properly_create_a_behat_process(): void
    {
        $process = $this->processFactory->createBehatProcess(
            '/var/www/html/test',
            'features/bootstrap',
            'some-php-parameters'
        );

        $this->assertSame(
            "'/usr/bin/php' 'some-php-parameters' 'vendor/bin/behat' 'features/bootstrap'",
            $process->getCommandLine()
        );
        $this->assertSame('/var/www/html/test', $process->getWorkingDirectory());
    }

    public function test_it_can_create_web_server_process_correctly(): void
    {
        $process = $this->processFactory->createWebServerProcess(
            '/var/www/html/test',
            'localhost',
            '8080'
        );

        $this->assertSame(
            "'/usr/bin/php' '-S' 'localhost:8080' '-t' '/var/www/html/test'",
            $process->getCommandLine()
        );
        $this->assertSame('/var/www/html/test', $process->getWorkingDirectory());
    }
}
