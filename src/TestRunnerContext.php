<?php

declare(strict_types=1);

namespace SEEC\BehatTestRunner\Context;

use Behat\Gherkin\Node\PyStringNode;
use Webmozart\Assert\Assert;

final class TestRunnerContext extends AbstractTestRunnerContext
{
    /**
     * @Given I have the configuration:
     */
    public function iHaveTheConfiguration(PyStringNode $input): void
    {
        $file = sprintf('%s/behat.yml', $this->getWorkingDirectory());
        $this->createFile($file, $input->getRaw());
    }

    /**
     * @Given I have the feature:
     */
    public function iHaveTheFeature(PyStringNode $input): void
    {
        $file = sprintf('%s/features/generated.feature', $this->getWorkingDirectory());
        $this->createFile($file, $input->getRaw());
    }

    /**
     * @Given I have the context:
     */
    public function iHaveTheContext(PyStringNode $input): void
    {
        $file = sprintf('%s/features/bootstrap/FeatureContext.php', $this->getWorkingDirectory());
        $this->createFile($file, $input->getRaw());
    }

    /**
     * @When I run Behat
     * @When /^I run Behat with "([^"]*)" parameter[s]?$/
     * @When /^I run Behat with "([^"]*)" parameter[s]? and with PHP CLI arguments "([^"]*)"$/
     * @When I run Behat with PHP CLI arguments :phpParameters
     */
    public function iRunBehat(string $parameters = '', string $phpParameters = ''): void
    {
        $this->runBehat($parameters, $phpParameters);
    }

    /**
     * @Given I have the file :filename in document root:
     */
    public function iHaveTheFileInDocumentRoot(string $filename, PyStringNode $content): void
    {
        $file = sprintf('%s/%s', $this->getDocumentRoot(), $filename);
        $this->createFile($file, $content->getRaw());
    }

    /**
     * @Given I have a web server running on host :hostname and port :port
     */
    public function iHaveAWebServerRunningOnAddressAndPort(string $hostname, int $port): void
    {
        $this->runWebServer($hostname, $port);
    }

    /**
     * @Then I should see a failing test
     * @Then I should see the tests failing
     */
    public function iShouldSeeAFailingTest(): void
    {
        $process = $this->getBehatProcess();
        Assert::notNull($process, 'Must start behat before asserting success');
        Assert::false($process->isSuccessful(), 'Behat found a failing scenario');
    }

    /**
     * @Then I should not see a failing test
     * @Then I should see the tests passing
     */
    public function iShouldNotSeeAFailingTest(): void
    {
        $process = $this->getBehatProcess();
        Assert::notNull($process, 'Must start behat before asserting success');
        Assert::true($process->isSuccessful(), 'Behat found a failing scenario');
    }
}
