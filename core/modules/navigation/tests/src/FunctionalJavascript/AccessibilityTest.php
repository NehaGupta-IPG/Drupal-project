<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\AccessibilityTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Performs accessibility tests for the navigation module.
 */
#[Group('navigation')]
#[Group('accessibility')]
#[RunTestsInSeparateProcesses]
class AccessibilityTest extends AccessibilityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['navigation'];

  /**
   * Setup callback for 'Admin page, mobile, sidebar open' test case.
   */
  protected function setupAdminPageMobileSidebarOpen(): void {
    $session = $this->getSession();

    $session->resizeWindow(
      500,
      800
    );

    // Wait and assert menu link is hidden.
    sleep(2);

    $menu_link = $session->getPage()->find('css', '#admin-toolbar')->findLink('Home page');
    $this->assertFalse($menu_link->isVisible());

    // Expand sidebar.
    $session->getPage()->pressButton('Expand sidebar');

    // Wait and assert menu link is visible.
    $this->assertTrue($menu_link->waitFor(2, function ($menu_link): bool {
      return $menu_link->isVisible();
    }));

    // Wait for sidebar to fully open before testing.
    sleep(1);
  }

  /**
   * {@inheritdoc}
   */
  public static function provider(): \Generator {

    yield 'Admin page, mobile, sidebar open' => [
      'path' => '/admin',
      'admin' => TRUE,
      'setup_callback' => 'setupAdminPageMobileSidebarOpen',
    ];
  }

}
