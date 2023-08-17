<?php

namespace Drupal\Tests\Core\Utility;

use PHPUnit\Framework\TestCase;

/**
 * Test legacy drupal_register_shutdown_function() function.
 *
 * @group Utility
 * @group legacy
 */
class LegacyShutdownTest extends TestCase {

  public function testDrupalRegisterShutdownFunction() {
    $this->expectDeprecation('drupal_register_shutdown_function() is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use \Drupal\Core\Shutdown\ShutdownHandler::get() or \Drupal\Core\Shutdown\ShutdownHandler::set() or \Drupal\Core\Shutdown\ShutdownHandler::reset() instead. See https://www.drupal.org/node/3053808');
    $this->assertEmpty(drupal_register_shutdown_function());
  }
}
