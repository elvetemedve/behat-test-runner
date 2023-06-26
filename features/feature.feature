Feature: Visiting a page on the website
  In order to demonstrate how to use test runner
  As a developer
  I should open a page and verify the content of it

  Background:
    Given I have the file "index.html" in document root:
            """
            <!DOCTYPE html>
            <html>
              <head>
                  <meta charset="UTF-8">
                  <title>Test page</title>
              </head>
              <body>
                  <h1>Lorem ipsum dolor amet.</h1>
              </body>
            </html>
            """
    And I have a web server running on host "localhost" and port "8080"
    And I have the feature:
            """
            Feature: Test runner demo feature
              Scenario:
                Given I open the index page
                Then I should see the content "Lorem ipsum" on the page in a h1 HTML tag
            """
    And I have the context:
            """
            <?php

            declare(strict_types=1);

            use Behat\Behat\Context\Context;
            use Webmozart\Assert\Assert;

            include '/var/www/html/vendor/autoload.php';

            final class FeatureContext implements Context
            {
                private array $clipboard = [];

                /**
                 * @return mixed
                 */
                private function get(string $key)
                {
                    Assert::keyExists($this->clipboard, $key, sprintf('Key "%s" does not exist in clipboard', $key));

                    return $this->clipboard[$key];
                }

                /**
                 * @var mixed $value
                 */
                private function set(string $key, $value)
                {
                    $this->clipboard[$key] = $value;
                }

                /**
                 * @Given I open the index page
                 */
                function firstStep()
                {
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, "http://localhost:8080/");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    $response = curl_exec($curl);
                    Assert::notNull($response, 'Response is null: ' . curl_error($curl));
                    $this->set('response', $response);
                    curl_close($curl);
                }

                /**
                 * @Then I should see the content :content on the page in a :tagName HTML tag
                 */
                function secondStep(string $content, string $tagName)
                {
                    $lastResponse = $this->get('response');
                    Assert::notNull($lastResponse, 'Response is null');

                    $pattern = '/<' . $tagName . '.*?>(.*?)<\/' . $tagName . '>/si';
                    preg_match($pattern, $lastResponse, $matches);
                    Assert::notSame(0, count($matches), 'No matches found');
                    Assert::notFalse(strpos($matches[1], $content), 'Content not found in tag');
                }
            }
            """

  Scenario: Visiting the index.html page
    When I run Behat
    Then I should not see a failing test
