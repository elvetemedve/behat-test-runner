<?php

declare(strict_types=1);

namespace SEEC\BehatTestRunner\Context\Components\ProcessFactory\Factory;

use SEEC\BehatTestRunner\Context\Components\ProcessFactory\Input\AbstractInput;
use Symfony\Component\Process\Process;

interface ProcessFactoryInterface
{
    public function createFromInput(AbstractInput $input): Process;
}
