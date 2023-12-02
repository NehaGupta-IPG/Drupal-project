<?php

namespace Drupal\Tests\Core\Utility;

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
    $this->expectDeprecation('drupal_register_shutdown_function() is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use \Drupal\Core\Shutdown\ShutdownHandler::get() or \Drupal\Core\Shutdown\ShutdownHandler::add() or \Drupal\Core\Shutdown\ShutdownHandler::reset() instead. See https://www.drupal.org/node/3053808');
    $this->assertEmpty(drupal_register_shutdown_function());
  }

}
