<?php

declare(strict_types=1);

namespace SEEC\Behat\Context\Services;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

final class ProcessFactory implements ProcessFactoryInterface
{
    /**
     * @var false|string
     */
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
            array_filter([$this->phpBin, $phpParameters, BEHAT_BIN_PATH, $parameters]), /** @phpstan-ignore-line */
            $workingDirectory
        );
    }

    public function createWebServerProcess(string $documentRoot, string $hostname, string $port): Process
    {
        Assert::notFalse($this->phpBin, 'Cannot find php executable, abort');

        return new Process(
            array_filter([$this->phpBin, '-S', sprintf('%s:%s', $hostname, $port), '-t', $documentRoot]),
            $documentRoot
        );
    }
}
