<?php

namespace Bex\Behat\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Bex\Behat\Context\Services\ProcessFactory;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Class TestRunnerContext
 *
 * This class provides steps to execute a secondary Behat process in order to test Behat extension under development.
 * It creates a test environment in the system temp directory.
 *
 * PHP's built-in web server is used to emulate a website for testing. Use steps from this context to create pages
 * in document root.
 * @example Create an index.html file with some content in it:
 * ```yml
 * Given I have the file "index.html" in document root:
 *  """
 *  <!DOCTYPE html>
 *  <html>
 *      <head>
 *          <meta charset="UTF-8">
 *          <title>Test page</title>
 *      </head>
 *
 *      <body>
 *          <h1>Lorem ipsum dolor amet.</h1>
 *      </body>
 *  </html>
 *  """
 * ```
 *
 * @example Start the web server:
 * ```yml
 * Given I have a web server running on host "localhost" and port "8080"
 * ```
 *
 * When Selenium2 driver is in use, a compatible browser is expected to be installed which will be controlled by tests.
 * Define the executable file of the browser in behat.yml by passing on the "browserCommand" parameter to this context
 * class.
 * @example PhantomJS is installed by Composer into the project bin directory. Add context to your project like this:
 * ```yml
 * default:
 *   suites:
 *     default:
 *       contexts:
 *         - TestRunnerContext:
 *           browserCommand: %paths.base%/bin/phantomjs --webdriver=4444
 * ```
 *
 * @author Geza Buza <bghome@gmail.com>
 * @license http://opensource.org/licenses/MIT The MIT License
 */
class TestRunnerContext implements SnippetAcceptingContext
{
    /** @var Filesystem $filesystem */
    private $filesystem;

    /** @var string $workingDirectory Place for generated tests */
    private $workingDirectory;

    /** @var string $documentRoot Root directory of the web server */
    private $documentRoot;

    /** @var Process[] $processes Active processes */
    private $processes = [];

    /** @var Process $behatProcess Active behat process */
    private $behatProcess;

    /** @var string $browserCommand */
    private $browserCommand;

    /** @var ProcessFactory $processFactory */
    private $processFactory;

    /**
     * TestRunnerContext constructor.
     *
     * @param string|null         $browserCommand Shell command which executes the tester browser
     * @param Filesystem|null     $fileSystem
     * @param ProcessFactory|null $processFactory
     */
    public function __construct(
        $browserCommand = null,
        Filesystem $fileSystem = null,
        ProcessFactory $processFactory = null
    ) {
        $this->browserCommand = $browserCommand;
        $this->filesystem = $fileSystem ?: new Filesystem();
        $this->processFactory = $processFactory ?: new ProcessFactory();
    }

    /**
     * @BeforeScenario
     */
    public function beforeRunTests()
    {
        $this->createWorkingDirectory();
    }

    /**
     * @AfterScenario
     */
    public function afterRunTests(AfterScenarioScope $scope)
    {
        $this->clearWorkingDirectory();
        $this->destroyProcesses();
        $this->printTesterOutputOnFailure($scope);
    }
    
    /**
     * @return void
     */
    public function createWorkingDirectory()
    {
        $this->workingDirectory = tempnam(sys_get_temp_dir(), 'behat-test-runner');
        $this->filesystem->remove($this->workingDirectory);
        $this->filesystem->mkdir($this->workingDirectory . '/features/bootstrap', 0770);

        $this->documentRoot = $this->workingDirectory .'/document_root';
        $this->filesystem->mkdir($this->documentRoot, 0770);
    }

    /**
     * @return void
     */
    public function clearWorkingDirectory()
    {
        $this->filesystem->remove($this->workingDirectory);
    }

    /**
     * @return void
     */
    public function destroyProcesses()
    {
        /** @var Process $process */
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop(10);
            }
        }

        $this->processes = [];
    }

    /**
     * @param  AfterScenarioScope $scope
     */
    public function printTesterOutputOnFailure($scope)
    {
        if (!$scope->getTestResult()->isPassed()) {
            $outputFile = sys_get_temp_dir() . '/behat-test-runner.out';
            $this->filesystem->dumpFile(
                $outputFile,
                $this->behatProcess->getOutput() . $this->behatProcess->getErrorOutput()
            );
            throw new RuntimeException("Output of secondary Behat process has been saved to $outputFile");
        }
    }

    /**
     * @Given I have the configuration:
     */
    public function iHaveTheConfiguration(PyStringNode $config)
    {
        $this->filesystem->dumpFile(
            $this->workingDirectory.'/behat.yml',
            $config->getRaw()
        );
    }

    /**
     * @Given I have the feature:
     */
    public function iHaveTheFeature(PyStringNode $content)
    {
        $this->filesystem->dumpFile(
            $this->workingDirectory.'/features/feature.feature',
            $content->getRaw()
        );
    }
    
    /**
     * @Given I have the context:
     */
    public function iHaveTheContext(PyStringNode $definition)
    {
        $this->filesystem->dumpFile(
            $this->workingDirectory.'/features/bootstrap/FeatureContext.php',
            $definition->getRaw()
        );
    }

    /**
     * @When I run Behat
     * @When /^I run Behat with "([^"]*)" parameter[s]?$/
     * @When /^I run Behat with "([^"]*)" parameter[s]? and with PHP CLI arguments "([^"]*)"$/
     * @When I run Behat with PHP CLI arguments :phpParams
     */
    public function iRunBehat($parameters = '', $phpParameters = '')
    {
        $this->runBehat($parameters, $phpParameters);
    }

    /**
     * @Given I have the file :filename in document root:
     */
    public function iHaveTheFileInDocumentRoot($filename, PyStringNode $content)
    {
        $this->filesystem->dumpFile($this->documentRoot .'/'. $filename, $content);
    }

    /**
     * @Given I have a web server running on host :hostname and port :port
     */
    public function iHaveAWebServerRunningOnAddressAndPort($hostname, $port)
    {
        $this->runWebServer($hostname, $port);
        $this->runBrowser();
    }

    /**
     * @Then I should see a failing test
     */
    public function iShouldSeeAFailingTest()
    {
        if ($this->behatProcess->isSuccessful()) {
            throw new RuntimeException('Behat did not find any failing scenario.');
        }
    }

    /**
     * @Then I should not see a failing test
     */
    public function iShouldNotSeeAFailingTest()
    {
        if (!$this->behatProcess->isSuccessful()) {
            throw new RuntimeException('Behat found a failing scenario.');
        }
    }

    /**
     * Returns the output of Behat command
     *
     * @return string
     */
    public function getStandardOutputMessage()
    {
        return $this->behatProcess->getOutput();
    }

    /**
     * Returns the error output of Behat command
     *
     * @return string
     */
    public function getStandardErrorMessage()
    {
        return $this->behatProcess->getErrorOutput();
    }

    /**
     * @param string $parameters
     * @param string $phpParameters
     *
     * @return void
     */
    private function runBehat($parameters = '', $phpParameters = '')
    {
        $behatProcess = $this->processFactory->createBehatProcess($this->workingDirectory, $parameters, $phpParameters);
        $this->behatProcess = $behatProcess;
        $this->processes[] = $this->behatProcess;
        $behatProcess->run();
    }

    /**
     * @param  string $hostname
     * @param  string $port
     *
     * @return void
     */
    private function runWebServer($hostname, $port)
    {
        $webServerProcess = $this->processFactory->createWebServerProcess($this->documentRoot, $hostname, $port);
        $this->processes[] = $webServerProcess;
        $webServerProcess->start();
    }

    /**
     * @return void
     */
    private function runBrowser()
    {
        if (is_null($this->browserCommand)) {
            return;
        }

        $browserProcess = $this->processFactory->createBrowserProcess($this->browserCommand, $this->workingDirectory);
        $this->processes[] = $browserProcess;
        $browserProcess->start();
    }
}