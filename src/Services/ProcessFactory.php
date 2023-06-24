<?php

declare(strict_types=1);

namespace Bex\Behat\Context\Services;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

if (defined('BEHAT_BIN_PATH') === false) {
    define('BEHAT_BIN_PATH', 'vendor/bin/behat');
}

final class ProcessFactory implements ProcessFactoryInterface
{
    /** @var false|string */
    private $phpBin;

    public function __construct(
        ?PhpExecutableFinder $phpFinder = null
    ) {
        $this->phpBin = ($phpFinder ?: new PhpExecutableFinder())->find();
    }

    public function createBehatProcess(
        string $workingDirectory,
        string $parameters = '',
        string $phpParameters = ''
    ): Process {
        Assert::notFalse($this->phpBin, 'Cannot find php executable, abort');

        return new Process(
            [sprintf('%s %s %s %s', $this->phpBin, $phpParameters, escapeshellarg(BEHAT_BIN_PATH), $parameters)],
            $workingDirectory
        );
    }

    public function createWebServerProcess(string $documentRoot, string $hostname, string $port): Process
    {
        Assert::notFalse($this->phpBin, 'Cannot find php executable, abort');

        return new Process(
            [sprintf('exec %s -S %s:%s -t %s', $this->phpBin, $hostname, $port, $documentRoot)],
            $documentRoot
        );
    }

    public function createBrowserProcess(
        string $browserCommand,
        string $workingDirectory
    ): Process {
        return new Process(
            [sprintf('exec %s', escapeshellcmd($browserCommand))],
            $workingDirectory
        );
    }
}
