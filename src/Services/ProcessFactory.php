<?php

namespace Bex\Behat\Context\Services;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * This factory class creates various processes used by the tester
 *
 * @license http://opensource.org/licenses/MIT The MIT License
 */
class ProcessFactory
{
    private $phpBin;

    /**
     * @param PhpExecutableFinder|null $phpFinder
     */
    public function __construct(PhpExecutableFinder $phpFinder = null)
    {
        $phpFinder = $phpFinder ?: new PhpExecutableFinder();
        $this->phpBin = $phpFinder->find();
    }

    /**
     * @param  string $workingDirectory
     * @param  string $parameters
     *
     * @return Process
     */
    public function createBehatProcess($workingDirectory, $parameters = '')
    {
        return new Process(
            sprintf('%s %s %s', $this->phpBin, escapeshellarg(BEHAT_BIN_PATH), $parameters),
            $workingDirectory
        );
    }

    /**
     * @param  string $documentRoot
     * @param  string $hostname
     * @param  string $port
     *
     * @return Process
     */
    public function createWebServerProcess($documentRoot, $hostname, $port)
    {
        $hostname = escapeshellarg($hostname);
        $port = escapeshellarg($port);
        $documentRoot = escapeshellarg($documentRoot);

        return new Process(
            sprintf('%s -S %s:%s -t %s', $this->phpBin, $hostname, $port, $documentRoot),
            $documentRoot
        );
    }

    /**
     * @param  string $browserCommand
     * @param  string $workingDirectory
     *
     * @return Process
     */
    public function createBrowserProcess($browserCommand, $workingDirectory)
    {
        return new Process(
            escapeshellcmd($browserCommand),
            $workingDirectory
        );
    }
}