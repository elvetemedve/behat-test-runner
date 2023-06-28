<?php

declare(strict_types=1);

namespace SEEC\Behat\Tests\Services;

use PHPUnit\Framework\TestCase;
use SEEC\Behat\Context\Components\ProcessFactory\Factory\ProcessFactory;
use SEEC\Behat\Context\Components\ProcessFactory\Factory\ProcessFactoryInterface;
use SEEC\Behat\Context\Components\ProcessFactory\Input\AbstractInput;
use SEEC\Behat\Context\Components\ProcessFactory\Input\BehatInput;
use SEEC\Behat\Context\Components\ProcessFactory\Input\WebserverInput;

if (defined('BEHAT_BIN_PATH') === false) {
    define('BEHAT_BIN_PATH', 'vendor/bin/behat');
}

final class ProcessFactoryTest extends TestCase
{
    private ProcessFactoryInterface $processFactory;

    public function setUp(): void
    {
        $this->processFactory = new ProcessFactory();
    }

    public static function inputProvider(): array
    {
        return [
            'Behat Input' => [
                new BehatInput(
                    'features/bootstrap',
                    'some-php-parameters'
                ),
            ],
            'Webserver Input' => [
                new WebserverInput(
                    'localhost',
                    8080,
                    '/var/www/html/test'
                ),
            ],
        ];
    }

    /**
     * @dataProvider inputProvider
     */
    public function test_it_will_correctly_generate_processes_with_right_schema(AbstractInput $input): void
    {
        $process = $this->processFactory->createFromInput($input);
        $expectationArray = array_filter([
            $input->getExecutor() ? sprintf("'%s'", $input->getExecutor()) : null,
            $input->getExecutorParameters() ? sprintf("'%s'", $input->getExecutorParameters()) : null,
            $input->getCommand() ? sprintf("'%s'", $input->getCommand()) : null,
            $input->getParameters() ? sprintf("'%s'", $input->getParameters()) : null,
            $input->getExtraParameters() ? sprintf("'%s'", $input->getExtraParameters()) : null,
        ]);

        $this->assertSame(implode(' ', $expectationArray), $process->getCommandLine());
        $this->assertSame($input->getDirectory(), $process->getWorkingDirectory());
    }
}
