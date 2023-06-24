<?php

declare(strict_types=1);

namespace Bex\Behat\Context\Services;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ProcessFactory implements ProcessFactoryInterface
{
    /** @var false|string */
    private $phpBin;

    public function __construct(?PhpExecutableFinder $phpFinder = null)
    {
        $phpFinder = $phpFinder ?: new PhpExecutableFinder();
        $this->phpBin = $phpFinder->find();
    }

    public function createBehatProcess(
        string $workingDirectory,
        string $parameters = '',
        string $phpParameters = ''
    ): Process {
        return new Process(
            sprintf('%s %s %s %s', $this->phpBin, $phpParameters, escapeshellarg(BEHAT_BIN_PATH), $parameters),
            $workingDirectory
        );
    }

    public function createWebServerProcess(string $documentRoot, string $hostname, string $port): Process
    {
        $hostname = escapeshellarg($hostname);
        $port = escapeshellarg($port);
        $documentRoot = escapeshellarg($documentRoot);

        return new Process(
            sprintf('exec %s -S %s:%s -t %s', $this->phpBin, $hostname, $port, $documentRoot),
            $documentRoot
        );
    }

    public function createBrowserProcess(
        string $browserCommand,
        string $workingDirectory
    ): Process {
        return new Process(
            sprintf("exec %s", escapeshellcmd($browserCommand)),
            $workingDirectory
        );
    }
}
