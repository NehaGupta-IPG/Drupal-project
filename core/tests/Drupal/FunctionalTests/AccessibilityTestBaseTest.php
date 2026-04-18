<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests;

use Behat\Mink\Exception\ExpectationException;
use Drupal\FunctionalJavascriptTests\AccessibilityTestBase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\FunctionalJavascriptTests\AccessibilityTestBase.
 */
#[CoversClass(AccessibilityTestBase::class)]
#[Group('accessibility')]
#[RunTestsInSeparateProcesses]
class AccessibilityTestBaseTest extends AccessibilityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['accessibility_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * @todo Remove this class property after https://drupal.org/i/3584314/.
   */
  protected $failOnJavascriptConsoleErrors = FALSE;

  /**
   * Setup function for 'Smoke test with Axe options' case.
   *
   * Prepares for failed assertion in AccessibilityTestBase::testPages.
   */
  protected function setupSmokeTestWithOptions(): void {
    global $base_url;

    $expected_message = <<<TEXT
Accessibility test failures (1 total)

1. [serious] Elements must meet minimum color contrast ratio thresholds
Test URL: BASE_URL/admin/accessibility-test
Axe rule: `color-contrast`
Violating targets (1):
  * `#target-low-contrast`
Failed asserting that an array is empty.
TEXT;
    $expected_message = str_replace("BASE_URL", $base_url, $expected_message);

    $this->expectException(AssertionFailedError::class);
    $this->expectExceptionMessage($expected_message);
  }

  /**
   * Setup function for 'Page not found check' case.
   *
   * Prepares for failed assertion in AccessibilityTestBase::testPages.
   */
  protected function setupPageNotFound(): void {
    $expected_message = 'The string "Page not found" appears in the HTML response of this page, but it should not.';
    $this->expectException(ExpectationException::class);
    $this->expectExceptionMessage($expected_message);
  }

  /**
   * Setup function for 'Access denied check ...' cases.
   *
   * Prepares for failed assertion in AccessibilityTestBase::testPages.
   */
  protected function setupAccessDenied(): void {
    $expected_message = 'The string "Access denied" appears in the HTML response of this page, but it should not.';
    $this->expectException(ExpectationException::class);
    $this->expectExceptionMessage($expected_message);
  }

  /**
   * {@inheritdoc}
   */
  public static function provider(): array {
    return [
      'Smoke test with Axe options' => [
        'uri' => 'admin/accessibility-test',
        'admin' => TRUE,
        'axe_options' => [
          'runOnly' => ['color-contrast'],
        ],
        'setup_callback' => 'setupSmokeTestWithOptions',
      ],
      'Page not found check' => [
        'uri' => '123456',
        'setup_callback' => 'setupPageNotFound',
      ],
      'Access denied check, with $admin argument default' => [
        'uri' => 'admin',
        'setup_callback' => 'setupAccessDenied',
      ],
      'Access denied check, with $admin argument FALSE' => [
        'uri' => 'admin',
        'admin' => FALSE,
        'setup_callback' => 'setupAccessDenied',
      ],
    ];
  }

}
