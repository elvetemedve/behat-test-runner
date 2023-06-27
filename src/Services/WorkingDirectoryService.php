<?php

declare(strict_types=1);

namespace SEEC\Behat\Context\Services;

use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Assert\Assert;

final class WorkingDirectoryService implements WorkingDirectoryServiceInterface
{
    private ?string $workingDirectory;

    private Filesystem $filesystem;

    private ?bool $autogenerated = null;

    private ?string $documentRoot = null;

    private ?string $featureDirectory = null;

    private bool $isInitialized = false;

    public function __construct(
        string $workingDirectory = null,
        Filesystem $filesystem = null
    ) {
        $this->workingDirectory = $workingDirectory;
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function isAutoGeneratedWorkingDirectory(): bool
    {
        if ($this->autogenerated === null) {
            $this->autogenerated = $this->workingDirectory === null;
        }

        return $this->autogenerated;
    }

    public function getWorkingDirectory(): ?string
    {
        Assert::nullOrString($this->workingDirectory);

        return $this->workingDirectory;
    }

    public function setWorkingDirectory(?string $workingDirectory): void
    {
        $this->workingDirectory = $workingDirectory;
    }

    public function getDocumentRoot(): ?string
    {
        Assert::nullOrString($this->documentRoot);

        return $this->documentRoot;
    }

    public function getFeatureDirectory(): ?string
    {
        Assert::nullOrString($this->featureDirectory);

        return $this->featureDirectory;
    }

    public function isInitialized(): bool
    {
        return $this->isInitialized;
    }

    public function createWorkingDirectory(): void
    {
        if ($this->isInitialized()) {
            return;
        }

        $workingDirectory = $this->getWorkingDirectory();
        if ($workingDirectory !== null && $this->filesystem->exists($workingDirectory)) {
            return;
        }

        if ($workingDirectory === null) {
            $workingDirectory = tempnam(sys_get_temp_dir(), 'behat-test-runner');
            Assert::notFalse($workingDirectory, 'Could not create temporary directory for test runner.');
            Assert::string($workingDirectory);
            $this->setWorkingDirectory($workingDirectory);
            $this->filesystem->remove($workingDirectory);
        }

        Assert::notNull($workingDirectory);
        Assert::string($workingDirectory);
        $this->filesystem->mkdir($workingDirectory, 0770);

        $this->documentRoot = sprintf('%s/document_root', $workingDirectory);
        if ($this->filesystem->exists($this->documentRoot) === false) {
            $this->filesystem->mkdir($this->documentRoot, 0770);
        }

        $this->featureDirectory = sprintf('%s/features/bootstrap', $workingDirectory);
        if ($this->filesystem->exists($this->featureDirectory) === false) {
            $this->filesystem->mkdir($this->featureDirectory, 0770);
        }
        $this->isInitialized = true;
    }

    public function clearWorkingDirectory(): void
    {
        $directory = $this->getWorkingDirectory();
        Assert::notNull($directory);
        Assert::string($directory);
        $this->filesystem->remove($directory);
    }
}