<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Test;

use Drupal\Component\Utility\Environment;
use Drupal\KernelTests\KernelTestBase;

/**
 * Performs Kernel tests on Drupal\Component\Utility\Environment::setTimeLimit.
 *
 * @group Test
 */
class EnvironmentSetTimeLimitTest extends KernelTestBase {

  /**
   * Tests the deprecation of \Drupal\Component\Utility\Environment::setTimeLimit()
   *
   * @group legacy
   */
  public function testDeprecatedTestSetTimeLimit(): void {
    $time_limit = Environment::setTimeLimit(240);
    $this->assertFalse($time_limit);
    $this->expectDeprecation('Drupal\Component\Utility\Environment::setTimeLimit() is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3483359');

    ini_set('max_execution_time', 1);
    $time_limit = Environment::setTimeLimit(240);
    $this->assertTrue($time_limit);
    $this->expectDeprecation('Drupal\Component\Utility\Environment::setTimeLimit() is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3483359');
  }

}
