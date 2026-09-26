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
   * page doesn't work in the CI environment: the link never passes the
   * visibility assertion, even with a very long timeout or a sleep()
   * before the assertion.
   *
   * @todo Determine why loading the page first doesn't work and fix it.
   */
  public function testAxeScanAdminPageMobileSidebarOpen(): void {
    $session = $this->getSession();
    $assert_session = $this->assertSession();

    // Set the viewport before Navigation and page load.
    $session->resizeWindow(500, 800);

    $this->drupalLogin($this->drupalCreateUser(admin: TRUE));
    $this->drupalGet('/admin');

    // A mobile page initially loads with its sidebar closed.
    $content_link_selector = '.toolbar-button--icon--navigation-content';
    $assert_session->assertNotVisibleInViewport('css', $content_link_selector);

    $session->getPage()->pressButton('Expand sidebar');

    // The Content link is visible after expanding the sidebar.
    $assert_session->waitForElementVisible('css', $content_link_selector);

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
