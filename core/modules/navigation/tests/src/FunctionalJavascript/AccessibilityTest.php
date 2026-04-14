<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\AccessibilityTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Performs accessibility tests for the navigation module.
 */
#[Group('navigation')]
#[Group('accessibility')]
#[RunTestsInSeparateProcesses]
class AccessibilityTest extends AccessibilityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['navigation'];

  /**
   * {@inheritdoc}
   *
   * @todo Remove this class property after https://drupal.org/i/3584314/.
   */
  protected $failOnJavascriptConsoleErrors = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function provider(): array {
    return [
      'Claro page' => [
        '/user/1/edit',
        TRUE,
      ],
    ];
  }

}
