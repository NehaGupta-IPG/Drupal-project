<?php

namespace Drupal\Tests\Core\Utility;

use Drupal\Core\Shutdown\ShutdownHandler;
use Drupal\Tests\UnitTestCase;

/**
 * Test legacy drupal_register_shutdown_function() function.
 *
 * Tests deprecation message.
 *
 * @group Utility
 * @group legacy
 */
class LegacyShutdownTest extends UnitTestCase {

  /**
   * Test legacy drupal_register_shutdown_function() function.
   */
  public function testDrupalRegisterShutdownFunction() {
    $a = static function (int $b = 0): int {
      static $a;
      return $a += $b;
    };
    $add = 10;
    $count = 0;
    $this->expectDeprecation('drupal_register_shutdown_function() is deprecated in drupal:10.3.0 and is removed from drupal:11.0.0. Use \Drupal\Core\Shutdown\ShutdownHandler::get() or \Drupal\Core\Shutdown\ShutdownHandler::add() or \Drupal\Core\Shutdown\ShutdownHandler::reset() instead. See https://www.drupal.org/node/3053808');
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
