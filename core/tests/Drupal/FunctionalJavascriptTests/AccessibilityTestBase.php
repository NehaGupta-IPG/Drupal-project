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
   * Test some pages.
   *
   * @param string $path
   *   The path to be tested. This will be passed to $this->drupalGet().
   * @param bool $admin
   *   (optional) Whether or not to log in as admin for the test.
   * @param array $axe_options
   *   (optional) Associative array of Axe options.
   * @param string|null $setup_callback
   *   (optional) Name of callback method to run page setup steps before
   *   executing Axe.
   *   The method must non-private and non-static. Return values from the method are
   *   not used.
   *
   * @see \Drupal\Tests\UiHelperTrait::drupalGet()
   */
  #[DataProvider('provider')]
  public function testAxeScan(string $path, bool $admin = FALSE, array $axe_options = [], ?string $setup_callback = NULL): void {
    if ($admin) {
      $this->drupalLogin($this->drupalCreateUser(admin: TRUE));
    }

    $this->drupalGet($path);

    if (is_callable([$this, $setup_callback])) {
      $this->{$setup_callback}();
    }

    // Missing modules or routes and access issues can cause a 403 or 404,
    // which means the wrong page is being tested and will likely result in
    // falsely-passing tests.
    $this->assertSession()
      ->responseNotContains('Page not found');
    $this->assertSession()
      ->responseNotContains('Access denied');

    $this->executeAxe($axe_options);
  }

  /**
   * Data provider for testPages.
   */
  abstract public static function provider(): iterable;

}
