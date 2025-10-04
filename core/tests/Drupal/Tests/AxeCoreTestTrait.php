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
   * @param array $options
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
   *   ```.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *
   * @see https://github.com/dequelabs/axe-core/blob/develop/doc/API.md#options-parameter
   */
  protected function executeAxe(array $options = []): void {
    $session = $this->getSession();

    // Load axe-core script.
    $axe_src = file_get_contents(DRUPAL_ROOT . '/core/node_modules/axe-core/axe.min.js');
    $session->executeScript($axe_src);

    // Run axe then wait for the results.
    $options_json = json_encode((object) $options);

    // Code is wrapped in IIFE to get single return value.
    // async/await forces synchronous execution.
    $axe_run = $session->evaluateScript(<<<JS
      await (async () => {
        out = {};
        try {
          out.results = await axe.run('body', {$options_json});
        }
        catch (e) {
          out.err = e.message;
        }
        return out;
      })()
    JS);

    // `@` avoids "Undefined array key" warning when assigning.
    @['results' => $results, 'err' => $error] = $axe_run;

    $this->assertEmpty(
      $error,
      sprintf("Axe encountered errors\n\n%s", $error)
    );

    // Check that Axe did return something overall.
    $this->assertNotEmpty($results, 'Axe returned no results. This should not have happened.');

    // @todo Include `incomplete` results, as they are warnings that need review.
    // @see https://docs.deque.com/devtools-for-web/4/en/needs-review-incomplete
    $this->assertEmpty(
      $results['violations'],
      $this->formatViolationsReport($results)
    );
  }

  /**
   * Formats the Axe violations report.
   *
   * @param array $results
   *   Results array returned from `axe.run()`.
   *
   * @see https://github.com/dequelabs/axe-core/blob/develop/doc/API.md#result-arrays
   */
  protected function formatViolationsReport(array $results): string {
    $message = sprintf('Accessibility test failures (%d total)', count($results['violations']));

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
   *
   * @see https://github.com/dequelabs/axe-core/blob/develop/doc/API.md#result-arrays
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

}
