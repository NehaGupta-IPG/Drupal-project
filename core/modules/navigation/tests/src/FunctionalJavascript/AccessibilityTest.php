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
   * Setup callback for 'Claro page, mobile, sidebar open' test case.
   */
  protected function setupClaroPageMobileSidebarOpen(): void {
    $session = $this->getSession();

    $session->resizeWindow(
      500,
      800
    );

    $create_button = $session->getPage()->find('css', '#admin-toolbar')->findButton('Create');

    $this->assertFalse($create_button->isVisible());
    $session->getPage()->pressButton('Expand sidebar');
    // Wait for CSS transition.
    $session->wait(500);
    $this->assertTrue($create_button->isVisible());
  }

  /**
   * {@inheritdoc}
   */
  public static function provider(): \Generator {

    yield 'Claro page' => [
      '/user/1/edit',
      TRUE,
    ];

    yield 'Claro page, mobile, sidebar open' => [
      'path' => '/admin',
      'admin' => TRUE,
      'setup_callback' => 'setupClaroPageMobileSidebarOpen',
    ];
  }

}
