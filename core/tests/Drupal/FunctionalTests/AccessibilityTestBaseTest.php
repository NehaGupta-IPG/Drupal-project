<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests;

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
   * Prepare for failed assertion in AccessibilityTestBase::testPages.
   */
  public function setUp():void {
    parent::setUp();

    global $base_url;

    $expected_message = <<<TEXT
Accessibility test failures (1 total)

1. [serious] Elements must meet minimum color contrast ratio thresholds
Test URL: BASE_URL/accessibility-test
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
   * {@inheritdoc}
   */
  public static function provider(): array {
    return [
      'Smoke test with Axe options' => [
        'uri' => 'accessibility-test',
        'admin' => FALSE,
        'axe_options' => [
          'runOnly' => ['color-contrast'],
        ],
      ],
    ];
  }

}
