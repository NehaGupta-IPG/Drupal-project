<?php

declare(strict_types=1);

namespace Drupal\Tests\dblog\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;

/**
 * Tests the permission update to the Watchdog view in the 112002 update.
 *
 * @group dblog
 */
class UpdateWatchdogViewPermissionsTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    // Note that contributed modules must use an absolute path of
    // DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-8.bare.standard.php.gz'
    // to drupal-8.bare.standard.php.gz, because the relative path to core in
    // the testbot is not guaranteed to be the same as what you use on your site.
    // If however you are writing a core test residing in (for example)
    // /core/modules/foo/src/Tests/Update, a relative path of
    // __DIR__ . '/../../../../system/tests/fixtures/update/drupal-8.bare.standard.php.gz'
    // is preferred.
    $this->databaseDumpFiles = [
      DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../fixtures/112002/dblog-112002-watchdog-permissions-change.php',
    ];
  }

  /**
   * Tests the Watchdog view's permissions before the 112002 update.
   */
  public function testUpdateHook112002PreUpdate(): void {
    // Load the Watchdog view.
    $watchdog_view = View::load('watchdog');

    if ($watchdog_view instanceof View) {
      // Get the permission used on the default display.
      $current_perm = $watchdog_view
        ->getDisplay('default')['display_options']['access']['options']['perm'];

      $this->assertEquals('access site reports', $current_perm);
    }
  }

  /**
   * Tests the Watchdog view's permissions after the 112002 update.
   */
  public function testUpdateHook112002PostUpdate(): void {
    // Run the updates.
    $this->runUpdates();

    // Load the Watchdog view.
    $watchdog_view = View::load('watchdog');

    if ($watchdog_view instanceof View) {
      // Get the permission used on the default display.
      $current_perm = $watchdog_view
        ->getDisplay('default')['display_options']['access']['options']['perm'];

      $this->assertEquals('access dblog reports', $current_perm);
    }
  }

}
