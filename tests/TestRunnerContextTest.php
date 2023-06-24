<?php

declare(strict_types=1);

namespace SEEC\Behat\Tests;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Testwork\Hook\Scope\AfterTestScope;
use Behat\Testwork\Tester\Result\TestResult;
use Bex\Behat\Context\Services\ProcessFactoryInterface;
use Bex\Behat\Context\TestRunnerContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class TestRunnerContextTest extends TestCase
{
    /** @var MockObject|Filesystem */
    private $fileSystem;

    /** @var MockObject|ProcessFactoryInterface */
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

    public function test_it_creates_the_working_directory(): void
    {
        $this->testRunnerContext->beforeRunTests();

        $this->assertNotEmpty($this->testRunnerContext->getWorkingDirectory());
        $this->assertDirectoryExists($this->testRunnerContext->getWorkingDirectory() . '/features/bootstrap');
        $this->assertDirectoryExists($this->testRunnerContext->getWorkingDirectory() . '/document_root');
    }

    public function test_it_fills_and_cleans_the_working_directory_correctly(): void
    {
        $this->test_it_creates_the_working_directory();

        $this->testRunnerContext->createFile('test1.log', 'test1');
        $this->testRunnerContext->createFile('test2.log', 'test2');

        $this->testRunnerContext->clearWorkingDirectory();

        $this->assertDirectoryExists($this->testRunnerContext->getWorkingDirectory());
        $resolve = glob($this->testRunnerContext->getWorkingDirectory() . '/*.*');
        $this->assertEmpty($resolve);
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
        $this->testRunnerContext->iHaveTheConfiguration($mockConfig);
        $this->assertFileExists('/var/www/html/test/behat.yml');
    }

    public function test_it_can_create_feature_files_correctly(): void
    {
        $mockConfig = new PyStringNode(['test'], 1);
        $this->testRunnerContext->iHaveTheFeature($mockConfig);
        $this->assertFileExists('/var/www/html/test/features/feature.feature');
    }

    public function test_it_can_create_context_files_correctly(): void
    {
        $mockConfig = new PyStringNode(['test'], 1);
        $this->testRunnerContext->iHaveTheContext($mockConfig);
        $this->assertFileExists('/var/www/html/test/features/bootstrap/FeatureContext.php');
    }

    public function test_it_can_create_files_in_document_root(): void
    {
        $mockConfig = new PyStringNode(['test'], 1);
        $this->testRunnerContext->iHaveTheFileInDocumentRoot('test.log', $mockConfig);
        $this->assertFileExists('/test.log');
    }

    public function test_it_can_successfully_create_and_start_webserver(): void
    {
        $mockProcess1 = $this->createMock(Process::class);
        $this->processFactory->expects($this->once())
            ->method('createWebServerProcess')
            ->with('/var/www/html/test/document_root', '', '')
            ->willReturn($mockProcess1);
        $mockProcess1->expects($this->once())
            ->method('start');

        $mockProcess2 = $this->createMock(Process::class);
        $this->processFactory->expects($this->once())
            ->method('createBrowserProcess')
            ->with('bin/phantomjs', '/var/www/html/test')
            ->willReturn($mockProcess2);
        $mockProcess2->expects($this->once())
            ->method('start');

        $this->testRunnerContext->iHaveAWebServerRunningOnAddressAndPort('', '');
    }

    public function test_it_will_use_behat_test_runner_directory_properly(): void
    {
        $this->testRunnerContext = new TestRunnerContext(
            'bin/phantomjs',
            $this->fileSystem,
            $this->processFactory
        );

        $this->fileSystem->remove('/var/www/html/test/*');
        $this->testRunnerContext->createWorkingDirectory();
        $resolve = $this->testRunnerContext->getWorkingDirectory();
        $this->assertStringStartsWith('/tmp/behat-test-runner', $resolve);
    }

    public function test_it_can_successfully_create_webserver_but_not_utilize_browser(): void
    {
        $this->testRunnerContext = new TestRunnerContext(
            null,
            null,
            $this->processFactory,
            '/var/www/html/test'
        );

        $mockProcess = $this->createMock(Process::class);
        $this->processFactory->expects($this->once())
            ->method('createWebServerProcess')
            ->with('/var/www/html/test/document_root', '', '')
            ->willReturn($mockProcess);

        $mockProcess->expects($this->once())->method('start');
        $this->processFactory->expects($this->never())->method('createBrowserProcess');

        $this->testRunnerContext->iHaveAWebServerRunningOnAddressAndPort('', '');
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
