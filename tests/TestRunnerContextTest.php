<?php

declare(strict_types=1);

namespace SEEC\BehatTestRunner\Tests;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Testwork\Hook\Scope\AfterTestScope;
use Behat\Testwork\Tester\Result\TestResult;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SEEC\BehatTestRunner\Context\Components\ProcessFactory\Factory\ProcessFactoryInterface;
use SEEC\BehatTestRunner\Context\Components\ProcessFactory\Input\BehatInput;
use SEEC\BehatTestRunner\Context\Components\ProcessFactory\Input\WebserverInput;
use SEEC\BehatTestRunner\Context\Services\WorkingDirectoryServiceInterface;
use SEEC\BehatTestRunner\Context\TestRunnerContext;
use SEEC\PhpUnit\Helper\ConsecutiveParams;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

if (defined('BEHAT_BIN_PATH') === false) {
    define('BEHAT_BIN_PATH', 'vendor/bin/behat');
}

final class TestRunnerContextTest extends TestCase
{
    use ConsecutiveParams;

    /** @var object|MockObject|Filesystem */
    private object $fileSystem;

    /** @var object|MockObject|ProcessFactoryInterface */
    private object $processFactory;

    /** @var object|MockObject|WorkingDirectoryServiceInterface */
    private object $directoryService;

    /** @var Finder|MockObject|object */
    private object $finder;

    private TestRunnerContext $testRunnerContext;

    public function setUp(): void
    {
        $this->processFactory = $this->createMock(ProcessFactoryInterface::class);
        $this->fileSystem = $this->createMock(Filesystem::class);
        $this->directoryService = $this->createMock(WorkingDirectoryServiceInterface::class);
        $this->finder = $this->createMock(Finder::class);
        $this->testRunnerContext = new TestRunnerContext(
            $this->fileSystem,
            $this->processFactory,
            $this->directoryService,
            '/var/www/html/test',
            $this->finder
        );
    }

    public function test_it_creates_the_working_directory(): void
    {
        $this->directoryService->expects($this->once())
            ->method('createWorkingDirectory');

        $this->testRunnerContext->beforeRunTests();
    }

    public function test_it_will_go_trough_defined_tear_down_methods_correctly(): void
    {
        $mockScope = $this->createMock(AfterTestScope::class);

        $this->directoryService->expects($this->once())
            ->method('getDocumentRoot')
            ->willReturn('/test');
        $this->finder->expects($this->once())
            ->method('in')
            ->with('/test')
            ->willReturnSelf();
        $this->fileSystem->expects($this->once())
            ->method('remove')
            ->with('/test');

        $this->testRunnerContext->afterRunTests($mockScope);
    }

    public static function fileCreatorProvider(): array
    {
        return [
            'Behat Config File' => [
                'iHaveTheConfiguration',
                'behat.yml',
            ],
            'Behat Feature File' => [
                'iHaveTheFeature',
                'features/generated.feature',
            ],
            'Behat Context File' => [
                'iHaveTheContext',
                'features/bootstrap/FeatureContext.php',
            ],
        ];
    }

    /** @dataProvider fileCreatorProvider */
    public function test_it_will_create_files_accordingly(string $methodName, string $fileName): void
    {
        $basePath = '/var/www/html/test';
        $this->directoryService->expects($this->once())
            ->method('getWorkingDirectory')
            ->willReturn($basePath);

        $expectedFileName = sprintf('%s/%s', $basePath, $fileName);
        $this->fileSystem->expects($this->once())
            ->method('dumpFile')
            ->with($expectedFileName, 'test');
        $input = new PyStringNode(['test'], 1);
        $this->testRunnerContext->$methodName($input);
    }

    public function test_it_will_start_behat_and_destory_it_on_process_end(): void
    {
        $basePath = '/var/www/html/test';
        $this->directoryService->expects($this->once())
            ->method('getWorkingDirectory')
            ->willReturn($basePath);
        $process = $this->createMock(Process::class);
        $input = new BehatInput('', '', $basePath);
        $this->processFactory->expects($this->once())
            ->method('createFromInput')
            ->with($input)
            ->willReturn($process);

        $process->expects($this->once())
            ->method('run');

        $process->expects($this->once())
            ->method('isRunning')
            ->willReturn(true);
        $process->expects($this->once())
            ->method('stop')
            ->with(10);

        $this->testRunnerContext->iRunBehat('', '');
        $mockScope = $this->createMock(AfterTestScope::class);
        $mockResult = $this->createMock(TestResult::class);
        $mockScope->expects($this->once())
            ->method('getTestResult')
            ->willReturn($mockResult);
        $mockResult->expects($this->once())
            ->method('isPassed')
            ->willReturn(false);
        $process->expects($this->once())
            ->method('getOutput')
            ->willReturn('test 1');
        $process->expects($this->once())
            ->method('getErrorOutput')
            ->willReturn('test 2');

        $this->fileSystem->expects($this->once())
            ->method('dumpFile')
            ->with('/tmp/behat-test-runner.out', 'test 1test 2');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Output of secondary Behat process has been saved to /tmp/behat-test-runner.out');

        $this->directoryService->expects($this->once())
            ->method('getDocumentRoot')
            ->willReturn('/test');
        $this->finder->expects($this->once())
            ->method('in')
            ->with('/test')
            ->willReturnSelf();
        $this->fileSystem->expects($this->once())
            ->method('remove')
            ->with('/test');

        $this->testRunnerContext->afterRunTests($mockScope);
    }

    public function test_it_can_create_arbitrary_files_and_they_will_be_removed_after_the_test(): void
    {
        $basePath = '/var/www/html/test/document_root';
        $this->directoryService->expects($this->exactly(2))
            ->method('getDocumentRoot')
            ->willReturn($basePath);
        $this->fileSystem->expects($this->once())
            ->method('dumpFile')
            ->with('/var/www/html/test/document_root/test.txt', 'test');

        $this->fileSystem->expects($this->exactly(2))
            ->method('remove')
            ->with(...$this->withConsecutive(
                [['/var/www/html/test/document_root/test.txt']],
                [$basePath]
            ));

        $this->finder->expects($this->once())
            ->method('in')
            ->with($basePath)
            ->willReturnSelf();

        $this->testRunnerContext->iHaveTheFileInDocumentRoot('test.txt', new PyStringNode(['test'], 1));
        $this->testRunnerContext->afterRunTests($this->createMock(AfterTestScope::class));
    }

    public function test_it_will_start_a_webserver_as_expected(): void
    {
        $this->directoryService->expects($this->once())
            ->method('isInitialized')
            ->willReturn(false);
        $this->directoryService->expects($this->once())
            ->method('createWorkingDirectory');

        $basePath = '/var/www/html/test/document_root';
        $this->directoryService->expects($this->once())
            ->method('getDocumentRoot')
            ->willReturn($basePath);

        $mockProcess = $this->createMock(Process::class);
        $this->processFactory->expects($this->once())
            ->method('createFromInput')
            ->with(new WebserverInput('', 0, $basePath))
            ->willReturn($mockProcess);
        $mockProcess->expects($this->once())
            ->method('start');
        $mockProcess->expects($this->once())
            ->method('isRunning')
            ->willReturn(false);
        $mockProcess->expects($this->once())
            ->method('getErrorOutput')
            ->willReturn('test 1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('test 1');

        $this->testRunnerContext->iHaveAWebServerRunningOnAddressAndPort('', 0);
    }

    public function test_it_will_fail_when_test_was_successful_but_its_not_expected(): void
    {
        $basePath = '/var/www/html/test';
        $this->directoryService->expects($this->once())
            ->method('getWorkingDirectory')
            ->willReturn($basePath);
        $process = $this->createMock(Process::class);
        $this->processFactory->expects($this->once())
            ->method('createFromInput')
            ->with(new BehatInput('', '', $basePath))
            ->willReturn($process);
        $process->expects($this->once())
            ->method('run');

        $process->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(true);

        $this->expectExceptionMessage('Behat found a failing scenario');
        $this->expectException(InvalidArgumentException::class);

        $this->testRunnerContext->iRunBehat('', '');
        $this->testRunnerContext->iShouldSeeAFailingTest();
    }

    public function test_it_will_fail_when_test_is_failing_but_its_not_expected(): void
    {
        $basePath = '/var/www/html/test';
        $this->directoryService->expects($this->once())
            ->method('getWorkingDirectory')
            ->willReturn($basePath);
        $process = $this->createMock(Process::class);
        $this->processFactory->expects($this->once())
            ->method('createFromInput')
            ->with(new BehatInput('', '', $basePath))
            ->willReturn($process);
        $process->expects($this->once())
            ->method('run');

        $process->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(false);

        $this->expectExceptionMessage('Behat found a failing scenario');
        $this->expectException(InvalidArgumentException::class);

        $this->testRunnerContext->iRunBehat('', '');
        $this->testRunnerContext->iShouldNotSeeAFailingTest();
    }

    public function test_it_will_backup_existing_files_and_restore_them_automatically_afterwards(): void
    {
        $this->directoryService->expects($this->once())
            ->method('getWorkingDirectory')
            ->willReturn('/var/www/html/test');

        $this->fileSystem->expects($this->once())
            ->method('exists')
            ->with('/var/www/html/test/behat.yml')
            ->willReturn(true);

        $this->fileSystem->expects($this->exactly(2))
            ->method('copy')
            ->with(...$this->withConsecutive(
                ['/var/www/html/test/behat.yml', '/var/www/html/test/behat.yml.backup', true],
                ['/var/www/html/test/behat.yml.backup', '/var/www/html/test/behat.yml', true]
            ));
        $this->fileSystem->expects($this->once())
            ->method('dumpFile')
            ->with('/var/www/html/test/behat.yml', 'test');

        $this->directoryService->expects($this->once())
            ->method('getDocumentRoot')
            ->willReturn('/test');
        $this->finder->expects($this->once())
            ->method('in')
            ->with('/test')
            ->willReturnSelf();

        $this->fileSystem->expects($this->exactly(3))
            ->method('remove')
            ->with(...$this->withConsecutive(
                [['/var/www/html/test/behat.yml']],
                ['/test'],
                ['/var/www/html/test/behat.yml.backup']
            ));

        $content = new PyStringNode(['test'], 1);
        $this->testRunnerContext->iHaveTheConfiguration($content);
        $this->testRunnerContext->afterRunTests($this->createMock(AfterTestScope::class));
    }
}
