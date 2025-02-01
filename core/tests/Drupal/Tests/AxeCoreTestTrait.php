<?php

declare(strict_types=1);

namespace Drupal\Tests;

/**
 * Provides methods to run axe-core tests in the WebDriver.
 *
 * This trait is meant to be used only by Functional Javascript test classes.
 */
trait AxeCoreTestTrait {

  /**
   * Executes axe on the current session and checks the results.
   *
   * @param ?array $options
   *   (optional) An associative array of additional Axe options.
   *   The provided options will be passed to `axe.run()` as a Javascript object.
   *   Examples:
   *   ```php
   *   [
   *     'runOnly' => [
   *       'type' => 'rule',
   *       'values' => ['region'],
   *     ],
   *   ]
   *   ```
   *   ```php
   *   [
   *     'rules' => [
   *       'region' => ['enabled' => false]
   *     ]
   *   ]
   *   ```
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *
   * @see https://github.com/dequelabs/axe-core/blob/develop/doc/API.md#options-parameter
   */
  protected function executeAxe(?array $options = NULL): void {
    $session = $this->getSession();
    $driver = $session->getDriver();

    // Load axe-core script.
    $axeSrc = file_get_contents(DRUPAL_ROOT . '/core/node_modules/axe-core/axe.min.js');
    $driver->executeScript($axeSrc);

    // Run axe then wait for the results.
    $options_json = json_encode((object) $options);
    $driver->executeScript(<<<JS
      // Clear any results or errors from prior runs on same session.
      window.axe_results = undefined;
      window.axe_errors = undefined;

      // Analyze page.
      axe
        .run('body', {$options_json})
        .then((results) => {
          window.axe_results = results;
        })
        .catch((e) => {
          if (window.axe_errors) {
            window.axe_errors.push(e.message);
          } else {
            window.axe_errors = [e.message];
          }
        });
    JS);

    $driver->wait(1000, 'window.axe_results !== undefined || window.axe_errors !== undefined');

    // Retrieve any errors.
    $errors = $driver->evaluateScript('return window.axe_errors');

    $this->assertEmpty(
      $errors,
      $this->formatErrorsReport($errors)
    );

    // Retrieve the results.
    $results = $driver->evaluateScript('return window.axe_results');

    // Ensure axe returned its results.
    $this->assertNotEmpty($results, 'Axe did not return its results in time.');

    $this->assertEmpty(
      $results['violations'],
      $this->formatViolationsReport($results)
    );
  }

  /**
   * Formats the Axe violations report.
   *
   * @param array $results
   *   Array of violations returned from `axe.run()`.
   */
  protected function formatViolationsReport(array $results): string {
    $message = 'Accessibility test failures';
    $message .= ' (' . count($results['violations']) . ' total)';

    foreach ($results['violations'] as $key => $violation) {
      $message .= PHP_EOL . PHP_EOL;
      $message .= $key + 1 . ". [{$violation['impact']}] {$violation['help']}" . PHP_EOL;
      $message .= "Test URL: {$results['url']}" . PHP_EOL;
      $message .= "Axe rule: `{$violation['id']}`";

      if (!empty($violation['nodes'])) {
        $message .= PHP_EOL;
        $message .= $this->formatViolationTargets($violation['nodes']);
      }
    }

    return $message;
  }

  /**
   * Formats the individual nodes for the violations report.
   *
   * The `target` property is used instead of the `html` property brevity.
   *
   * @param array $nodes
   *   Array of nodes for a single violation.
   */
  protected function formatViolationTargets(array $nodes): string {
    $message = 'Violating targets';
    $message .= ' (' . count($nodes) . '):';

    foreach ($nodes as $node) {
      $message .= PHP_EOL;
      $message .= "  * `{$node['target'][0]}`";
    }

    return $message;
  }

  /**
   * Format the Axe errors report.
   *
   * @param ?array $errors
   *   Array of errors returned from `axe.run()`, or NULL.
   */
  protected function formatErrorsReport(?array $errors): string {

    // Function may be called by the respective assertion even
    // when there are no errors.
    if (!$errors) {
      return '';
    }

    $message = 'Axe encountered errors' . PHP_EOL;

    foreach ($errors as $key => $error) {
      $message .= PHP_EOL;
      $message .= $key + 1 . ". {$error}";
    }

    return $message;
  }

}
