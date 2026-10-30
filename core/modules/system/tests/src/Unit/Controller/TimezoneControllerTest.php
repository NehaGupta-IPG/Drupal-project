<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Unit\Controller;

use Drupal\system\Controller\TimezoneController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests TimezoneController::getTimezone().
 */
#[CoversClass(TimezoneController::class)]
#[Group('system')]
class TimezoneControllerTest extends UnitTestCase {

  /**
   * Tests that passing NULL triggers a deprecation and falls back to -1.
   */
  #[IgnoreDeprecations]
  public function testNullIsDaylightSavingTimeTriggersDeprecation(): void {
    $this->expectUserDeprecationMessage('Passing NULL to Drupal\system\Controller\TimezoneController::getTimezone() is deprecated in drupal:12.0.0 and will be removed in drupal:13.0.0. Pass -1 instead. See https://www.drupal.org/node/3585397');
    $controller = new TimezoneController();
    $response = $controller->getTimezone('BST', 3600, NULL);
    $this->assertSame(200, $response->getStatusCode());
  }

}
