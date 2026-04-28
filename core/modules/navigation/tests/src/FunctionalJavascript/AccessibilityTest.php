<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
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

    // Wait for and click 'Expand sidebar' button.
    $this
      ->assertSession()
      ->waitForElementVisible('named', ['button', 'Expand sidebar'])
      ->click();

    // Wait for toolbar 'Create' button to become visible after CSS transition.
    $session
      ->getPage()
      ->find('css', '#admin-toolbar')
      ->findButton('Create')
      ->waitFor(5, function (NodeElement $element) {
        return $element->isVisible();
      });
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
