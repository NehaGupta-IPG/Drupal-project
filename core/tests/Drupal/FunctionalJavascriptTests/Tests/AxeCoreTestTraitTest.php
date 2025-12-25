<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Tests;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\AxeCoreTestTrait;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests AxeCoreTestTrait.
 */
#[CoversClass(AxeCoreTestTrait::class)]
#[Group('Testing')]
#[RunTestsInSeparateProcesses]
class AxeCoreTestTraitTest extends WebDriverTestBase {

  use AxeCoreTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['accessibility_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Independent smoke test that Axe errors are reported as PHPUnit failures.
   */
  public function testAxeCoreTestTraitReportsErrors(): void {

    $this->drupalGet('accessibility-test');

    $this->expectException(AssertionFailedError::class);
    $this->expectExceptionMessage(<<<TEXT
Axe encountered errors

unknown rule `unknown-rule` in options.runOnly
Failed asserting that a string is empty.
TEXT
    );

    $this->executeAxe([
      'runOnly' => [
        'type' => 'rule',
        'values' => ['unknown-rule'],
      ],
    ]);
  }

  /**
   * Tests Axe call with no options.
   */
  public function testAxeCoreTestTraitDefaults(): void {
    global $base_url;

    $this->drupalGet('accessibility-test');

    $this->expectException(AssertionFailedError::class);
    $this->expectExceptionMessage(<<<TEXT
Accessibility test failures (2 total)

1. [serious] Elements must meet minimum color contrast ratio thresholds
Test URL: {$base_url}/accessibility-test
Axe rule: `color-contrast`
Violating targets (1):
  * `#target-low-contrast`

2. [critical] Form elements must have labels
Test URL: {$base_url}/accessibility-test
Axe rule: `label`
Violating targets (1):
  * `input`
Failed asserting that an array is empty.
TEXT
    );

    $this->executeAxe();
  }

  /**
   * Tests various `axe.run()` option combinations.
   */
  #[DataProvider('providerAxeCoreTestTraitWithOptions')]
  public function testAxeCoreTestTraitWithRuleOptions(array $axe_options, string $expected_message): void {
    global $base_url;

    $this->drupalGet('accessibility-test');

    $this->expectException(AssertionFailedError::class);
    $expected_message = str_replace("BASE_URL", $base_url, $expected_message);
    $this->expectExceptionMessage($expected_message);
    $this->executeAxe($axe_options);
  }

  /**
   * Tests that results do not bleed into subsequent runs on same session.
   */
  public function testAxeCoreTestTraitClearsPreviousResults(): void {

    // Violations
    $this->drupalGet('accessibility-test');

    // Expecting violations, but only on the first run.
    try {
      $this->executeAxe();
    }
    catch (AssertionFailedError $exception) {
      // Test setup requires existence of violations from this run.
      $this->assertStringContainsString(
        'Accessibility test failures',
        $exception->getMessage()
      );
    }

    // Run again on arbitrary tag that should not have violations or errors.
    // Should pass because violations from previous run are cleared.
    $this->executeAxe([
      'runOnly' => [
        'type' => 'tag',
        'values' => ['unknown-tag'],
      ],
    ]);

    // Errors
    $this->drupalGet('accessibility-test');

    // Expecting errors, but only on the first run.
    try {
      $this->executeAxe([
        'runOnly' => [
          'type' => 'rule',
          'values' => ['unknown-rule'],
        ],
      ]);
    }
    catch (AssertionFailedError $exception) {
      // Test setup requires existence of an error from this run.
      $this->assertStringContainsStringIgnoringCase(
        'unknown rule `unknown-rule` in options.runOnly',
        $exception->getMessage()
      );
    }

    // Run again on arbitrary tag that should not have violations or errors.
    // Should pass because errors from previous run are cleared.
    try {
      $this->executeAxe([
        'runOnly' => [
          'type' => 'tag',
          'values' => ['unknown-tag'],
        ],
      ]);
    }
    catch (AssertionFailedError $exception) {
      // If there is an assertion failure,
      // it should not contain prior error message.
      $this->assertStringNotContainsStringIgnoringCase(
        'unknown rule `unknown-rule` in options.runOnly',
        $exception->getMessage()
      );
      // If above assertion passes, it was an unexpected error.
      // Re-throw so PHPUnit can handle it.
      throw $exception;
    }
  }

  /**
   * Data provider.
   */
  public static function providerAxeCoreTestTraitWithOptions(): \Generator {
    // `BASE_URL` is replaced in the test because the global `$base_url` isn't
    // available here.
    yield '`color-contrast` rule disabled via `rules` option' => [
      [
        'rules' => [
          'color-contrast' => ['enabled' => FALSE],
        ],
      ],
      <<<TEXT
Accessibility test failures (1 total)

1. [critical] Form elements must have labels
Test URL: BASE_URL/accessibility-test
Axe rule: `label`
Violating targets (1):
  * `input`
Failed asserting that an array is empty.
TEXT
    ];

    yield '`label` rule disabled via `runOnly` option' => [
      [
        'runOnly' => ['color-contrast'],
      ],
      <<<TEXT
Accessibility test failures (1 total)

1. [serious] Elements must meet minimum color contrast ratio thresholds
Test URL: BASE_URL/accessibility-test
Axe rule: `color-contrast`
Violating targets (1):
  * `#target-low-contrast`
Failed asserting that an array is empty.
TEXT
    ];

    yield 'tag set that limits results via `runOnly` option' => [
      [
        'runOnly' => [
          'type' => 'tag',
          'values' => ['wcag2a'],
        ],
      ],
      <<<TEXT
Accessibility test failures (1 total)

1. [critical] Form elements must have labels
Test URL: BASE_URL/accessibility-test
Axe rule: `label`
Violating targets (1):
  * `input`
Failed asserting that an array is empty.
TEXT
    ];
  }

}
