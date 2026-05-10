<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Error;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that deprecated errors.inc functions trigger deprecation notices.
 */
#[Group('Error')]
#[Group('legacy')]
#[RunTestsInSeparateProcesses]
class LegacyErrorLevelsTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once $this->getDrupalRoot() . '/core/includes/errors.inc';
  }

  /**
   * Tests that drupal_error_levels() is deprecated.
   */
  public function testDrupalErrorLevelsDeprecation(): void {
    $this->expectUserDeprecationMessage('drupal_error_levels() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Error\ErrorLevels::LEVELS or \Drupal\Core\Error\ErrorLevels::getSeverity() instead. See https://www.drupal.org/project/drupal/issues/3000229');
    $levels = drupal_error_levels();
    $this->assertIsArray($levels);
    $this->assertArrayHasKey(E_ERROR, $levels);
  }

  /**
   * Tests that error_displayable() is deprecated.
   */
  public function testErrorDisplayableDeprecation(): void {
    $this->expectUserDeprecationMessage('error_displayable() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Error\ErrorDisplayLevel::resolve()->isDisplayable() instead. See https://www.drupal.org/project/drupal/issues/3000229');
    // This will call _drupal_get_error_level() internally which also triggers
    // a deprecation, so we expect both.
    $this->expectUserDeprecationMessage('_drupal_get_error_level() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Error\ErrorDisplayLevel::resolve() instead. See https://www.drupal.org/project/drupal/issues/3000229');
    $result = error_displayable();
    $this->assertIsBool($result);
  }

  /**
   * Tests that _drupal_get_error_level() is deprecated.
   */
  public function testDrupalGetErrorLevelDeprecation(): void {
    $this->expectUserDeprecationMessage('_drupal_get_error_level() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Error\ErrorDisplayLevel::resolve() instead. See https://www.drupal.org/project/drupal/issues/3000229');
    $level = _drupal_get_error_level();
    $this->assertIsString($level);
  }

}
