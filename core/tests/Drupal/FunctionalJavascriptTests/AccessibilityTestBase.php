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
   * @param bool $admin
   *   (optional) Whether or not to log in as admin for the test.
   * @param array $axe_options
   *   (optional) Associative array of Axe options.
   * @param string|null $setup_callback
   *   (optional) Name of callback method to run page setup steps before
   *   executing Axe.
   *   The method must not be private. Return values from the method are
   *   not used.
   */
  #[DataProvider('provider')]
  public function testPages(string $uri, bool $admin = FALSE, array $axe_options = [], ?string $setup_callback = NULL): void {
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

    if (is_callable([$this, $setup_callback])) {
      $this->{$setup_callback}();
    }

    $this->executeAxe($axe_options);
  }

  /**
   * Data provider for testPages.
   */
  abstract public static function provider(): array;

}
