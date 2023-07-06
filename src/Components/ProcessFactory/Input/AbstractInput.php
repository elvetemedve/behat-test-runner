<?php

declare(strict_types=1);

namespace SEEC\Behat\Context\Components\ProcessFactory\Input;

use Webmozart\Assert\Assert;

abstract class AbstractInput
{
    private ?string $executor = null;

    private ?string $command = null;

    private ?string $parameters = null;

    private ?string $executorParameters = null;

    private ?string $directory = null;

    private ?string $extraParameters = null;

    private int $timeout = 600;

    public function getExecutor(): ?string
    {
        return $this->executor;
    }

    protected function setExecutor(?string $executor): void
    {
        $this->executor = $executor;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }

    protected function setCommand(?string $command): void
    {
        $this->command = $command;
    }

    public function getParameters(): ?string
    {
        return $this->parameters;
    }

    protected function setParameters(?string $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getDirectory(): ?string
    {
        return $this->directory;
    }

    protected function setDirectory(?string $directory): void
    {
        $this->directory = $directory;
    }

    public function getExecutorParameters(): ?string
    {
        return $this->executorParameters;
    }

    protected function setExecutorParameters(?string $executorParameters): void
    {
        $this->executorParameters = $executorParameters;
    }

    public function getExtraParameters(): ?string
    {
        return $this->extraParameters;
    }

    public function setExtraParameters(?string $parameters): void
    {
        $this->extraParameters = $parameters;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): void
    {
        Assert::greaterThan($timeout, 0, 'Timeout must be greater than 0');
        $this->timeout = $timeout;
    }
}
