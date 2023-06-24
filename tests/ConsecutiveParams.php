<?php

declare(strict_types=1);

namespace SEEC\Behat\Tests;

trait ConsecutiveParams
{
    // @see: https://stackoverflow.com/questions/75389000/replace-phpunit-method-withconsecutive
    // @see: https://stackoverflow.com/questions/21861825/quick-way-to-find-the-largest-array-in-a-multidimensional-array
    public function consecutiveParams(array ...$args): array
    {
        $callbacks = [];
        $count = count(max($args));

        for ($index = 0; $index < $count; ++$index) {
            $returns = [];

            foreach ($args as $arg) {
                if (!array_is_list($arg)) {
                    throw new \InvalidArgumentException('Every array must be a list');
                }

                if (!isset($arg[$index])) {
                    throw new \InvalidArgumentException(sprintf('Every array must contain %d parameters', $count));
                }

                $returns[] = $arg[$index];
            }

            $callbacks[] = $this->callback(new class($returns) {
                /** @var array */
                protected $returns = [];

                public function __construct(array $returns)
                {
                    $this->returns = $returns;
                }

                public function __invoke($actual): bool
                {
                    if (count($this->returns) === 0) {
                        return true;
                    }

                    return $actual === array_shift($this->returns);
                }
            });
        }

        return $callbacks;
    }
}
