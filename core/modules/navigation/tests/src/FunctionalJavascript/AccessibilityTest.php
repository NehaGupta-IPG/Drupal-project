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
   * Tests the 'Claro page, mobile, sidebar open' case.
   */
  public function testClaroPageMobileSidebarOpen(): void {
    $session = $this->getSession();

    // Set the viewport before Navigation and page load.
    $session->resizeWindow(500, 800);

    $this->drupalLogin($this->drupalCreateUser(admin: TRUE));
    $this->drupalGet('/admin');

    $menu_button = $session->getPage()
      ->find('css', '#admin-toolbar')
      ->findButton('Create');

    // A mobile page initially loads with its sidebar closed.
    $this->assertTrue($menu_button->waitFor(2, function ($menu_button): bool {
      return !$menu_button->isVisible();
    }));

    $session->getPage()->pressButton('Expand sidebar');

    // The Create button is visible after expanding the sidebar.
    $this->assertTrue($menu_button->waitFor(2, function ($menu_button): bool {
      return $menu_button->isVisible();
    }));

    $this->assertSession()->responseNotContains('Page not found');
    $this->assertSession()->responseNotContains('Access denied');

    $this->executeAxe();
  }

  /**
   * {@inheritdoc}
   */
  public static function provider(): \Generator {

    yield 'Claro page' => [
      '/user/1/edit',
      TRUE,
    ];

  }

}
