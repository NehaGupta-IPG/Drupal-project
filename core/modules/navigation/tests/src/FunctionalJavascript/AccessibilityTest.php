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
   * Tests the 'Admin page, mobile, sidebar open' case.
   *
   * This is a standalone test because resizing the window after loading the
   * page doesn't work in the CI environment: the button never passes the
   * isVisible() assertion, even with a very long timeout or a sleep()
   * before the assertion.
   *
   * @todo Determine why loading the page first doesn't work and fix it.
   */
  public function testAxeScanAdminPageMobileSidebarOpen(): void {
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

    // Sleep prevents intermittent flakiness from scanning too quickly.
    sleep(1);
    $this->executeAxe();
  }

  /**
   * {@inheritdoc}
   */
  public static function provider(): \Generator {

    yield 'Admin page' => [
      'path' => '/user/1/edit',
      'admin' => TRUE,
      'axe_options' => [
        'rules' => [
          // @todo remove after https://drupal.org/i/3583486.
          'region' => ['enabled' => FALSE],
        ],
      ],
    ];
  }

}
