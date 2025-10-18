<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Bootstrap;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that front-controllers without symfony/runtime keep working.
 *
 * Symfony Runtime is introduced to change bootstrap logic. This should be done
 * as a backward-compatible change. This test ensures that applications that
 * still use the old model of index.php (initializing the DrupalKernel,
 * creating the request, and dispatching it) will keep working.
 */
#[Group('browsertestbase')]
#[RunTestsInSeparateProcesses]
class LegacyFrontControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['legacy_front_controller_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that a simple request is routed by the front-controller correctly.
   */
  public function testSuccessfulFrontControllerRequest() : void {
    $module_path = $this->getModulePath('legacy_front_controller_test');

    $this->drupalGet("$module_path/test_index.php/test");
    $this->assertSession()->pageTextContains('Hello World');
  }

}
