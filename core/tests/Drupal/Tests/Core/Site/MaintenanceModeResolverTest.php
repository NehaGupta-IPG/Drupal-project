<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Site;

use Drupal\Core\Site\MaintenanceModeResolver;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the MaintenanceModeResolver.
 */
#[Group('Site')]
class MaintenanceModeResolverTest extends UnitTestCase {

  /**
   * Tests isMaintenance() returns FALSE when not in maintenance mode.
   */
  public function testNotInMaintenance(): void {
    $resolver = new MaintenanceModeResolver();

    // Without MAINTENANCE_MODE constant and without a container,
    // the resolver should return FALSE.
    $this->assertFalse($resolver->isMaintenance());
  }

}
