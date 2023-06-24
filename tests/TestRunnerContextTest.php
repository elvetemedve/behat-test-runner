<?php

declare(strict_types=1);

namespace SEEC\Tests;

use Bex\Behat\Context\Services\ProcessFactoryInterface;
use Bex\Behat\Context\TestRunnerContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class TestRunnerContextTest extends TestCase
{
    /** @var Filesystem */
    private $fileSystem;

    /** @var ProcessFactoryInterface */
    private $processFactory;

    /** @var TestRunnerContext */
    private $testRunnerContext;

    public function setUp(): void
    {
        $this->processFactory = $this->createMock(ProcessFactoryInterface::class);
        $this->fileSystem = new Filesystem();
        $this->testRunnerContext = new TestRunnerContext(
            'bin/phantomjs',
            $this->fileSystem,
            $this->processFactory,
            '/var/www/html/test'
        );
    }

    public function testCreateWorkingDirectory(): void
    {
        $this->testRunnerContext->createWorkingDirectory();

        $this->assertNotEmpty($this->testRunnerContext->getWorkingDirectory());
        $this->assertDirectoryExists($this->testRunnerContext->getWorkingDirectory() . '/features/bootstrap');
        $this->assertDirectoryExists($this->testRunnerContext->getWorkingDirectory() . '/document_root');
    }

    public function testClearWorkingDirectory(): void
    {
        $this->testCreateWorkingDirectory();

        $this->testRunnerContext->createFile('test1.log', 'test1');
        $this->testRunnerContext->createFile('test2.log', 'test2');
        $this->testRunnerContext->clearWorkingDirectory();
        $this->assertDirectoryExists($this->testRunnerContext->getWorkingDirectory());
        $resolve = glob( $this->testRunnerContext->getWorkingDirectory() . '/*.*');
        $this->assertEmpty($resolve);
    }

    public function testDestroyProcesses(): void
    {
        $process1 = $this->createMock(Process::class);
        $process1->expects($this->once())->method('isRunning')->willReturn(true);
        $process1->expects($this->once())->method('stop')->with(10);
        $this->testRunnerContext->addProcess($process1);

        $process2 = $this->createMock(Process::class);
        $process2->expects($this->once())->method('isRunning')->willReturn(false);
        $this->testRunnerContext->addProcess($process2);

        $this->testRunnerContext->destroyProcesses();

        $this->assertEmpty($this->testRunnerContext->getProcesses());
    }
}
