<?php

declare(strict_types=1);

namespace SEEC\BehatTestRunner\Context;

use Behat\Behat\Context\Context;
use Behat\Testwork\Hook\Scope\AfterTestScope;
use RuntimeException;
use SEEC\BehatTestRunner\Context\Components\ProcessFactory\Factory\ProcessFactory;
use SEEC\BehatTestRunner\Context\Components\ProcessFactory\Factory\ProcessFactoryInterface;
use SEEC\BehatTestRunner\Context\Components\ProcessFactory\Input\BehatInput;
use SEEC\BehatTestRunner\Context\Components\ProcessFactory\Input\WebserverInput;
use SEEC\BehatTestRunner\Context\Services\WorkingDirectoryService;
use SEEC\BehatTestRunner\Context\Services\WorkingDirectoryServiceInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

abstract class AbstractTestRunnerContext implements Context, TestRunnerContextInterface
{
    private Filesystem $filesystem;

    private ProcessFactoryInterface $processFactory;

    private WorkingDirectoryServiceInterface $directoryService;

    private ?Process $behatProcess = null;

    private Finder $finder;

    private array $processes = [];

    private array $files = [];

    private array $backupFiles = [];

    public function __construct(
        ?Filesystem $fileSystem = null,
        ?ProcessFactoryInterface $processFactory = null,
        ?WorkingDirectoryServiceInterface $workingDirectoryService = null,
        ?string $workingDirectory = null,
        ?Finder $finder = null
    ) {
        $this->filesystem = $fileSystem ?: new Filesystem();
        $this->processFactory = $processFactory ?: new ProcessFactory();
        $this->directoryService = $workingDirectoryService ?: new WorkingDirectoryService($workingDirectory);
        $this->finder = $finder ?? new Finder();
    }

    /**
     * @BeforeScenario
     */
    public function beforeRunTests(): void
    {
        $this->createWorkingDirectory();
    }

    /**
     * @AfterScenario
     */
    public function afterRunTests(AfterTestScope $scope): void
    {
        $this->clearWorkingDirectory();
        $this->destroyProcesses();
        $this->printTesterOutputOnFailure($scope);
    }

    protected function createWorkingDirectory(): void
    {
        $this->getDirectoryService()->createWorkingDirectory();
    }

    protected function createFile(string $fileName, string $content): void
    {
        if ($this->filesystem->exists($fileName)) {
            $backupFileName = $fileName . '.backup';
            $this->filesystem->copy($fileName, $backupFileName, true);
            $this->backupFiles[$fileName] = $backupFileName;
        }

        $this->filesystem->dumpFile($fileName, $content);
        $this->files[] = $fileName;
    }

    protected function clearWorkingDirectory(): void
    {
        $files = $this->files;
        if ($files !== []) {
            $this->filesystem->remove($files);
            $this->files = [];
        }

        $directoryRoot = $this->getDocumentRoot();
        if ($directoryRoot && $this->filesystem->exists($directoryRoot)) {
            $result = $this->finder->in($directoryRoot);
            if (count($result) === 0) {
                $this->filesystem->remove($directoryRoot);
            }
        }

        $backupFiles = $this->backupFiles;
        foreach ($backupFiles as $original => $backupFile) {
            $this->filesystem->copy($backupFile, $original, true);
            $this->filesystem->remove($backupFile);
        }
    }

    protected function destroyProcesses(): void
    {
        foreach ($this->getProcesses() as $process) {
            if ($process->isRunning()) {
                $process->stop(10);
            }
        }

        $this->processes = [];
    }

    protected function printTesterOutputOnFailure(AfterTestScope $scope): void
    {
        $process = $this->getBehatProcess();
        if ($process instanceof Process && !$scope->getTestResult()->isPassed()) {
            $outputFile = sys_get_temp_dir() . '/behat-test-runner.out';
            $outputContent = $process->getOutput() . $process->getErrorOutput();
            $this->filesystem->dumpFile(
                $outputFile,
                $outputContent
            );

            echo $outputContent . \PHP_EOL;

            throw new RuntimeException("Output of secondary Behat process has been saved to $outputFile");
        }
    }

    protected function getDirectoryService(): WorkingDirectoryServiceInterface
    {
        return $this->directoryService;
    }

    protected function addProcess(Process $process): void
    {
        $this->processes[] = $process;
    }

    protected function getProcesses(): array
    {
        return $this->processes;
    }

    protected function getWorkingDirectory(): ?string
    {
        return $this->getDirectoryService()->getWorkingDirectory();
    }

    protected function getDocumentRoot(): ?string
    {
        return $this->getDirectoryService()->getDocumentRoot();
    }

    protected function getBehatProcess(): ?Process
    {
        return $this->behatProcess;
    }

    protected function runBehat(string $parameters = '', string $phpParameters = ''): void
    {
        $workingDirectory = $this->getWorkingDirectory();
        Assert::string($workingDirectory);

        $process = $this->processFactory->createFromInput(
            new BehatInput($phpParameters, $parameters, $workingDirectory)
        );
        $this->behatProcess = $process;
        $this->addProcess($process);
        $process->run();
    }

    protected function runWebServer(string $hostname, int $port): void
    {
        if ($this->getDirectoryService()->isInitialized() === false) {
            $this->createWorkingDirectory();
        }

        $documentRoot = $this->getDocumentRoot();
        Assert::string($documentRoot);

        $process = $this->processFactory->createFromInput(new WebserverInput($hostname, $port, $documentRoot));
        $this->addProcess($process);
        $process->start();

        Assert::same($process->isRunning(), true, $process->getErrorOutput());
    }
}
