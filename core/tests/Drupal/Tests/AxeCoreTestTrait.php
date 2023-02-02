<?php

namespace Drupal\Tests;

/**
 * Provides methods to run axe-core tests in the WebDriver.
 *
 * This trait is meant to be used only by Functional Javascript test classes.
 */
trait AxeCoreTestTrait {

  /**
   * @var array Violations' impacts to be considered as a failure.
   */
  protected $axeViolationImpactFailures = [
    'minor' => FALSE,
    'moderate' => TRUE,
    'serious' => TRUE,
    'critical' => TRUE,
  ];

  /**
   * Setter to disable failures for a given violation impact.
   *
   * @param string $impact
   *   The violation impact to disable failures for.
   */
  protected function disableFailuresForImpact(string $impact) {
    assert(array_key_exists($impact, $this->axeViolationImpactFailures), '$impact is not a valid value');
    $this->axeViolationImpactFailures[$impact] = FALSE;
  }

  /**
   * Setter to enable failures for a given violation impact.
   *
   * @param string $impact
   *   The violation impact to enable failures for.
   */
  protected function enableFailuresForImpact(string $impact) {
    assert(array_key_exists($impact, $this->axeViolationImpactFailures), '$impact is not a valid value');
    $this->axeViolationImpactFailures[$impact] = TRUE;
  }

  /**
   * Executes axe on the current session and check the results.
   *
   * Violations' impacts are tested against the axeViolationImpactFailures
   * attribute before considering it a test failure.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   */
  protected function executeAxe() {
    $session = $this->getSession();
    $driver = $session->getDriver();

    // Load axe-core script.
    $axeSrc = file_get_contents(DRUPAL_ROOT . '/core/node_modules/axe-core/axe.min.js');
    $driver->executeScript($axeSrc);

    // Run axe then wait for the results.
    $driver->executeScript('axe.run().then(results => window.axe_results = results)');
    $driver->wait(1000, 'window.axe_results !== undefined');

    // Retrieve the results and clean things up in case we run another test in
    // the same page.
    $results = $driver->evaluateScript('return window.axe_results');
    $driver->executeScript('window.axe_results = undefined');

    // Ensure axe returned its results.
    $this->assertNotEmpty($results, 'Axe did not return its results in time.');

    // Handle violations.
    if (!empty($results['violations'])) {
      foreach ($results['violations'] as $violation) {
        // Check if the violation's impact should be considered a failure.
        if (!empty($this->axeViolationImpactFailures[$violation['impact']])) {
          $this->assertEmpty($violation, '[' . $violation['impact'] . '] ' . $violation['description']);
        }
      }
    }
  }

}
