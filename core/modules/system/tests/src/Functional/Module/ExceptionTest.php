<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Module;

use Drupal\Core\Database\Database;

/**
 * Tests the exception during installation of modules.
 *
 * @group Module
 */
class ExceptionTest extends ModuleTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['dblog'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests how module_enable handles exceptions.
   *
   * It expects exceptions to pass through and for them to be logged.
   */
  public function testInstallException(): void {
    // Check that dblog module is installed.
    $this->assertModules(['dblog'], TRUE);
    // Record total watchdog messages.
    $pre_count = Database::getConnection()->select('watchdog', 'wd')->countQuery()->execute()->fetchField();

    // Try and install the module.
    try {
      \Drupal::service('module_installer')->install(['common_test_install_helper'], FALSE);
      // If we get here, no exceptions were caught.
      $this->fail('An exception was not thrown');
    }
    catch (\Exception) {
      // Expected exception; just continue testing.
    }

    // Count watchdog messages and compare.
    $post_count = Database::getConnection()->select('watchdog', 'wd')->countQuery()->execute()->fetchField();

    // There should be one new message.
    $this->assertTrue($pre_count + 1 == $post_count, 'One new watchdog exception');

  }

}
