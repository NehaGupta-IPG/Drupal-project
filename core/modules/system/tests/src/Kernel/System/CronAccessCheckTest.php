<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\System;

use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Access\CronAccessCheck;

/**
 * CronAccessCheck access_checker tests.
 *
 * @group system
 * @group legacy
 */
class CronAccessCheckTest extends KernelTestBase {

  /**
   * Assert deprecation warnings for missing injected services.
   */
  public function testConstructorDeprecation(): void {
    $method = CronAccessCheck::class . '::__construct';
    $this->expectDeprecation('Calling ' . $method . ' without the $logger argument is deprecated in drupal:10.3.0 and it will be required in drupal:11.0.0. See https://www.drupal.org/node/3409252');
    $this->expectDeprecation('Calling ' . $method . ' without the $state argument is deprecated in drupal:10.3.0 and it will be required in drupal:11.0.0. See https://www.drupal.org/node/3409252');

    new CronAccessCheck();
  }

}
