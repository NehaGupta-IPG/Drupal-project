<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests;

use Drupal\Tests\AxeCoreTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Base class for running accessibility tests.
 */
abstract class AccessibilityTestBase extends WebDriverTestBase {

  use AxeCoreTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected $profile = 'nightwatch_a11y_testing';

  /**
   * {@inheritdoc}
   *
   * @todo Remove this class property after https://drupal.org/i/3584694.
   */
  protected $failOnJavascriptConsoleErrors = FALSE;

  /**
   * Test some pages.
   *
   * @param string $uri
   *   The path to be tested.
   * @param bool|null $admin
   *   (optional) Whether or not to log in as admin for the test.
   * @param array|null $axe_options
   *   (optional) Associative array of Axe options.
   * @param array|null $browser_options
   *   (optional) Associative array of browser options.
   *   Possible values:
   *   - 'resize_window': Width and height passed to Behat\Mink\Session::resizeWindow():
   *     - 'width': Integer pixel width.
   *     - 'height'  Integer pixel height.
   */
  #[DataProvider('provider')]
  public function testPages(string $uri, bool $admin = FALSE, array $axe_options = [], array $browser_options = []): void {
    if ($admin) {
      $this->drupalLogin($this->drupalCreateUser(admin: TRUE));
    }

    $this->drupalGet($uri);

    // Missing modules or routes and access issues can cause a 403 or 404,
    // which means the wrong page is being tested and will likely result in
    // falsely-passing tests.
    $this->assertSession()
      ->responseNotContains('Page not found');
    $this->assertSession()
      ->responseNotContains('Access denied');

    if (!empty($browser_options['resize_window'])) {
      $this->getSession()->resizeWindow(
        $browser_options['resize_window']['width'],
        $browser_options['resize_window']['height']
      );
    }

    $this->executeAxe($axe_options);
  }

  /**
   * Data provider for testPages.
   */
  abstract public static function provider(): array;

}
