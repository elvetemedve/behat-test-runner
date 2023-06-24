<?php

declare(strict_types=1);

namespace SEEC\Behat\Tests\Services;

use Bex\Behat\Context\Services\ProcessFactory;
use Bex\Behat\Context\Services\ProcessFactoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;

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

        if (defined('BEHAT_BIN_PATH') === false) {
            define('BEHAT_BIN_PATH', 'vendor/bin/behat');
        }

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
            "'/usr/bin/php some-php-parameters '\''vendor/bin/behat'\'' features/bootstrap'",
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
            "'exec /usr/bin/php -S localhost:8080 -t /var/www/html/test'",
            $process->getCommandLine()
        );
        $this->assertSame('/var/www/html/test', $process->getWorkingDirectory());
    }

    public function test_it_can_create_browser_process_as_expected(): void
    {
        $process = $this->processFactory->createBrowserProcess(
            'phantomjs',
            '/var/www/html/test',
        );

        $this->assertSame(
            "'exec phantomjs'",
            $process->getCommandLine()
        );
        $this->assertSame('/var/www/html/test', $process->getWorkingDirectory());
    }
}
