<?php

declare(strict_types=1);

namespace SEEC\BehatTestRunner\Context\Components\ProcessFactory\Factory;

use SEEC\BehatTestRunner\Context\Components\ProcessFactory\Input\AbstractInput;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

final class ProcessFactory implements ProcessFactoryInterface
{
    public function createFromInput(AbstractInput $input): Process
    {
        Assert::notNull($input->getExecutor(), 'Executable cannot be null, abort');
        Assert::notFalse($input->getExecutor(), 'Executable cannot be false, abort');

        return new Process(
            array_filter([
                $input->getExecutor(),
                $input->getExecutorParameters(),
                $input->getCommand(),
                $input->getParameters(),
                $input->getExtraParameters(),
            ]),
            $input->getDirectory(),
            null,
            null,
            $input->getTimeout()
        );
    }
}
