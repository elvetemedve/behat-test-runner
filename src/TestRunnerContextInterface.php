<?php

declare(strict_types=1);

namespace SEEC\BehatTestRunner\Context;

use Behat\Testwork\Hook\Scope\AfterTestScope;

interface TestRunnerContextInterface
{
    public function beforeRunTests(): void;

    public function afterRunTests(AfterTestScope $scope): void;
}
