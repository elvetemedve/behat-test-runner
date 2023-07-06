<?php

declare(strict_types=1);

namespace SEEC\Behat\Context\Components\ProcessFactory\Input;

use Symfony\Component\Process\PhpExecutableFinder;

final class WebserverInput extends AbstractInput
{
    public function __construct(
        string $hostname = 'localhost',
        int $port = 8080,
        string $workingDirectory = '',
        int $timeout = 600
    ) {
        $this->setExecutor((new PhpExecutableFinder())->find() ?: null);
        $this->setExecutorParameters('-S');
        $this->setCommand(sprintf('%s:%d', $hostname, $port));
        $this->setParameters('-t');
        $this->setExtraParameters($workingDirectory);
        $this->setDirectory($workingDirectory);
        $this->setTimeout($timeout);
    }
}
