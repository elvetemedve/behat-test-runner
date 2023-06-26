<?php

declare(strict_types=1);

namespace SEEC\Behat\Tests;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Testwork\Hook\Scope\AfterTestScope;
use Behat\Testwork\Tester\Result\TestResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SEEC\Behat\Context\Services\ProcessFactoryInterface;
use SEEC\Behat\Context\TestRunnerContext;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class TestRunnerContextTest extends TestCase
{
    use ConsecutiveParams;

    /** @var object|MockObject|Filesystem */
    private object $fileSystem;

    /** @var object|MockObject|ProcessFactoryInterface */
    private object $processFactory;

    private TestRunnerContext $testRunnerContext;

    public function setUp(): void
    {
        $this->processFactory = $this->createMock(ProcessFactoryInterface::class);
        $this->fileSystem = $this->createMock(Filesystem::class);
        $this->testRunnerContext = new TestRunnerContext(
            $this->fileSystem,
            $this->processFactory,
            '/var/www/html/test'
        );
    }

    public function test_it_creates_the_working_directory(): void
    {
        $matcher = $this->exactly(3);
        $this->fileSystem->expects($matcher)
            ->method('mkdir')
            ->with(...$this->consecutiveParams(
                [$this->testRunnerContext->getWorkingDirectory(), 504],
                [$this->testRunnerContext->getWorkingDirectory() . '/features/bootstrap', 504],
                [$this->testRunnerContext->getWorkingDirectory() . '/document_root', 504]
            ));

        $this->testRunnerContext->beforeRunTests();
        $resolve = $this->testRunnerContext->getWorkingDirectory();
        $this->assertIsString($resolve);
        $this->assertNotNull($resolve);
        $this->assertNotEmpty($resolve);
    }

    public function test_it_fills_and_cleans_the_working_directory_correctly(): void
    {
        $this->test_it_creates_the_working_directory();

        $this->fileSystem->expects($this->exactly(2))
            ->method('dumpFile')
            ->with(...$this->consecutiveParams(
                ['/var/www/html/test/test1.log', 'test1'],
                ['/var/www/html/test/test2.log', 'test2']
            ));
        $this->fileSystem->expects($this->once())
            ->method('remove')
            ->with(['/var/www/html/test/test1.log', '/var/www/html/test/test2.log', ]);

        $this->testRunnerContext->createFile('/var/www/html/test/test1.log', 'test1');
        $this->testRunnerContext->createFile('/var/www/html/test/test2.log', 'test2');

        $this->testRunnerContext->clearWorkingDirectory();
    }

    public function test_it_destroys_the_processes_correctly(): void
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

    public function test_it_cleans_up_after_tests(): void
    {
        $this->test_it_creates_the_working_directory();
        $this->testRunnerContext->createFile('test1.log', 'test1');
        $this->testRunnerContext->createFile('test2.log', 'test2');
        $process1 = $this->createMock(Process::class);
        $process1->expects($this->once())->method('isRunning')->willReturn(true);
        $process1->expects($this->once())->method('stop')->with(10);
        $this->testRunnerContext->addProcess($process1);
        $process2 = $this->createMock(Process::class);
        $process2->expects($this->once())->method('isRunning')->willReturn(false);
        $this->testRunnerContext->addProcess($process2);

        $mockResult = $this->createMock(TestResult::class);
        $mockScope = $this->createMock(AfterTestScope::class);

        $mockScope->expects($this->once())
            ->method('getTestResult')
            ->willReturn($mockResult);

        $mockResult->expects($this->once())
            ->method('isPassed')
            ->willReturn(false);

        /** @var MockObject|Process $mockProcess */
        $mockProcess = $this->mock_behat_process();
        $mockProcess->expects($this->once())
            ->method('getOutput')
            ->willReturn('');
        $mockProcess->expects($this->once())
            ->method('getErrorOutput')
            ->willReturn('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Output of secondary Behat process has been saved to /tmp/behat-test-runner.out');
        $this->testRunnerContext->iRunBehat('', '');
        $this->testRunnerContext->afterRunTests($mockScope);
        $this->assertDirectoryExists($this->testRunnerContext->getWorkingDirectory());
        $resolve = glob($this->testRunnerContext->getWorkingDirectory() . '/*.*');
        $this->assertEmpty($resolve);
        $this->assertEmpty($this->testRunnerContext->getProcesses());
    }

    public function test_it_will_return_error_message_as_expected(): void
    {
        /** @var MockObject|Process $mockProcess */
        $mockProcess = $this->mock_behat_process();
        $this->testRunnerContext->iRunBehat();
        $mockProcess->expects($this->once())
            ->method('getErrorOutput')
            ->willReturn('test');

        $resolve = $this->testRunnerContext->getStandardErrorMessage();
        $this->assertSame('test', $resolve);
    }

    public function test_it_will_return_output_message_as_expected(): void
    {
        /** @var MockObject|Process $mockProcess */
        $mockProcess = $this->mock_behat_process();
        $this->testRunnerContext->iRunBehat();
        $mockProcess->expects($this->once())
            ->method('getOutput')
            ->willReturn('test');

        $resolve = $this->testRunnerContext->getStandardOutputMessage();
        $this->assertSame('test', $resolve);
    }

    public function test_it_will_throw_an_exception_when_process_was_not_successful(): void
    {
        /** @var MockObject|Process $mockProcess */
        $mockProcess = $this->mock_behat_process();
        $this->testRunnerContext->iRunBehat();
        $mockProcess->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Behat found a failing scenario.');
        $this->testRunnerContext->iShouldNotSeeAFailingTest();
    }

    public function test_it_will_throw_an_exception_when_process_was_successful_but_was_expected_to_fail(): void
    {
        /** @var MockObject|Process $mockProcess */
        $mockProcess = $this->mock_behat_process();
        $this->testRunnerContext->iRunBehat();
        $mockProcess->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(true);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Behat did not find any failing scenario.');
        $this->testRunnerContext->iShouldSeeAFailingTest();
    }

    public function test_it_can_create_configuration_files_correctly(): void
    {
        $mockConfig = new PyStringNode(['test'], 1);
        $this->fileSystem->expects($this->once())
            ->method('dumpFile')
            ->with('/var/www/html/test/behat.yml', 'test');
        $this->testRunnerContext->iHaveTheConfiguration($mockConfig);
    }

    public function test_it_can_create_feature_files_correctly(): void
    {
        $mockConfig = new PyStringNode(['test'], 1);

        $this->fileSystem->expects($this->once())
            ->method('dumpFile')
            ->with('/var/www/html/test/features/feature.feature', 'test');
        $this->testRunnerContext->iHaveTheFeature($mockConfig);
    }

    public function test_it_can_create_context_files_correctly(): void
    {
        $mockConfig = new PyStringNode(['test'], 1);
        $this->fileSystem->expects($this->once())
            ->method('dumpFile')
            ->with('/var/www/html/test/features/bootstrap/FeatureContext.php', 'test');
        $this->testRunnerContext->iHaveTheContext($mockConfig);
    }

    public function test_it_can_create_files_in_document_root(): void
    {
        $mockConfig = new PyStringNode(['test'], 1);
        $this->fileSystem->expects($this->once())
            ->method('dumpFile')
            ->with('/test.log', 'test');
        $this->testRunnerContext->iHaveTheFileInDocumentRoot('test.log', $mockConfig);
    }

    public function test_it_can_successfully_create_and_start_webserver(): void
    {
        $mockProcess = $this->createMock(Process::class);
        $this->processFactory->expects($this->once())
            ->method('createWebServerProcess')
            ->with('/var/www/html/test/document_root', '', '')
            ->willReturn($mockProcess);
        $mockProcess->expects($this->once())
            ->method('start');
        $mockProcess->expects($this->once())
            ->method('isRunning')
            ->willReturn(true);

        $this->testRunnerContext->iHaveAWebServerRunningOnAddressAndPort('', '');
    }

    public function test_it_will_use_behat_test_runner_directory_properly(): void
    {
        $this->testRunnerContext = new TestRunnerContext(
            $this->fileSystem,
            $this->processFactory,
            null
        );

        $this->fileSystem->remove('/var/www/html/test/*');
        $this->testRunnerContext->createWorkingDirectory();
        $resolve = $this->testRunnerContext->getWorkingDirectory();
        $this->assertStringStartsWith('/tmp/behat-test-runner', $resolve);
    }

    public function tearDown(): void
    {
        $this->testRunnerContext->clearWorkingDirectory();
    }

    private function mock_behat_process(): Process
    {
        $mockProcess = $this->createMock(Process::class);
        $this->processFactory->expects($this->once())
            ->method('createBehatProcess')
            ->with('/var/www/html/test', '', '')
            ->willReturn($mockProcess);
        $mockProcess->expects($this->once())
            ->method('run');

        return $mockProcess;
    }
}
