<?php

declare(strict_types=1);

namespace SEEC\Behat\Context\Components\ProcessFactory\Factory;

use SEEC\Behat\Context\Components\ProcessFactory\Input\AbstractInput;
use Symfony\Component\Process\Process;

interface ProcessFactoryInterface
{
    public function createFromInput(AbstractInput $input): Process;
}
