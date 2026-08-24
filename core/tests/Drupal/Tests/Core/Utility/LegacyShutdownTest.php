<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Utility;

use Drupal\Core\Shutdown\ShutdownHandler;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test legacy drupal_register_shutdown_function() function.
 *
 * Tests deprecation message.
 */
#[Group('Utility')]
#[Group('legacy')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class LegacyShutdownTest extends UnitTestCase {

  /**
   * Test legacy drupal_register_shutdown_function() function.
   */
  public function testDrupalRegisterShutdownFunction(): void {
    $a = static function (int $b = 0): int {
      static $counter = 0;
      $counter += $b;
      return $counter;
    };
    $add = 10;
    $count = 0;
    $this->expectUserDeprecationMessage('drupal_register_shutdown_function() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Shutdown\ShutdownHandler::get() or \Drupal\Core\Shutdown\ShutdownHandler::add() or \Drupal\Core\Shutdown\ShutdownHandler::reset() instead. See https://www.drupal.org/node/3053808');
    $this->assertCount(++$count, drupal_register_shutdown_function($a, $add));
    $this->assertCount(++$count, $callbacks = &drupal_register_shutdown_function($a, $add));
    foreach ($callbacks as $callback_array) {
      $this->assertIsArray($callback_array);
      $this->assertCount(2, $callback_array);
    }
    ShutdownHandler::getInstance()->shutdown();
    $this->assertSame($a(), $add * $count);
  }

}
