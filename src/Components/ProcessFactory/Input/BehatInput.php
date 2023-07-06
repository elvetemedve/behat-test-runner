<?php

declare(strict_types=1);

namespace SEEC\Behat\Context\Components\ProcessFactory\Input;

use Symfony\Component\Process\PhpExecutableFinder;

final class BehatInput extends AbstractInput
{
    public function __construct(
        string $executorParameters = '',
        string $parameters = '',
        string $workingDirectory = '/tmp',
        int $timeout = 600
    ) {
        $this->setExecutor((new PhpExecutableFinder())->find() ?: null);
        $this->setExecutorParameters($executorParameters);
        $this->setCommand(BEHAT_BIN_PATH); /** @phpstan-ignore-line */
        $this->setParameters($parameters);
        $this->setDirectory($workingDirectory);
        $this->setTimeout($timeout);
    }
}
