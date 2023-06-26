<?php

declare(strict_types=1);

namespace SEEC\Behat\Context\Services;

use Symfony\Component\Process\Process;

interface ProcessFactoryInterface
{
    public function createBehatProcess(
        string $workingDirectory,
        string $parameters = '',
        string $phpParameters = ''
    ): Process;

    public function createWebServerProcess(string $documentRoot, string $hostname, string $port): Process;
}
