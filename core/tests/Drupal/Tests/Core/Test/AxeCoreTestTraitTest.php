<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Test;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\AxeCoreTestTrait;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Tests AxeCoreTestTrait.
 *
 * @coversDefaultClass \Drupal\Tests\AxeCoreTestTrait
 * @group Testing
 */
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
   * Tests Axe call with no options.
   */
  public function testAxeCoreTestTraitDefaults(): void {

    $this->drupalGet('accessibility-test');

    $this->expectException(AssertionFailedError::class);
    $this->expectExceptionMessage(<<<TEXT
Accessibility test failures (2 total)

1. [serious] Elements must meet minimum color contrast ratio thresholds
Test URL: http://web/accessibility-test
Axe rule: `color-contrast`
Violating targets (1):
  * `span`

2. [critical] Form elements must have labels
Test URL: http://web/accessibility-test
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
   *
   * @dataProvider providerAxeCoreTestTraitWithOptions
   */
  public function testAxeCoreTestTraitWithRuleOptions(array $axe_options, string $expected_message): void {
    $this->drupalGet('accessibility-test');

    $this->expectException(AssertionFailedError::class);
    $this->expectExceptionMessage($expected_message);
    $this->executeAxe($axe_options);
  }

  /**
   * Tests that Axe errors are reported as PHPUnit failures.
   */
  public function testAxeCoreTestTraitReportsErrors(): void {

    $this->drupalGet('accessibility-test');

    $this->expectException(AssertionFailedError::class);
    $this->expectExceptionMessage(<<<TEXT
Axe encountered errors

1. unknown rule `unknown-rule` in options.runOnly
Failed asserting that an array is empty.
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
      // Other assertion failures can be swallowed.
      $this->assertStringNotContainsStringIgnoringCase(
        'unknown rule `unknown-rule` in options.runOnly',
        $exception->getMessage()
      );
    }
  }

  /**
   * Data provider.
   *
   * @return \Generator
   *   Test scenarios.
   */
  public static function providerAxeCoreTestTraitWithOptions(): \Generator {
    yield '`color-contrast` rule disabled via `rules` option' => [
      [
        'rules' => [
          'color-contrast' => ['enabled' => FALSE],
        ],
      ],
      <<<TEXT
Accessibility test failures (1 total)

1. [critical] Form elements must have labels
Test URL: http://web/accessibility-test
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
Test URL: http://web/accessibility-test
Axe rule: `color-contrast`
Violating targets (1):
  * `span`
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
Test URL: http://web/accessibility-test
Axe rule: `label`
Violating targets (1):
  * `input`
Failed asserting that an array is empty.
TEXT
    ];
  }

}
