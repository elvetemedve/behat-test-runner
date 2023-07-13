<?php

declare(strict_types=1);

namespace SEEC\BehatTestRunner\Context\Services;

interface WorkingDirectoryServiceInterface
{
    public function getWorkingDirectory(): ?string;

    public function setWorkingDirectory(?string $workingDirectory): void;

    public function getDocumentRoot(): ?string;

    public function getFeatureDirectory(): ?string;

    public function isInitialized(): bool;

    public function createWorkingDirectory(): void;

    public function clearWorkingDirectory(): void;
}
