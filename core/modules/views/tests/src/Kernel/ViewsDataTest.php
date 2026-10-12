<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel;

use Drupal\TestTools\Attribute\ShareContainer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the service views.views_data.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
#[ShareContainer(FALSE)]
class ViewsDataTest extends ViewsKernelTestBase {

  /**
   * Tests that the service "views.views_data" is backend-overridable.
   */
  public function testViewsViewsDataIsBackendOverridable(): void {
    $definition = $this->container->getDefinition('views.views_data');
    $this->assertTrue($definition->hasTag('backend_overridable'));
  }

}
