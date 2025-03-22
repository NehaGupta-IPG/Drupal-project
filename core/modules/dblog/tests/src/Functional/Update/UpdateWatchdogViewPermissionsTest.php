<?php

declare(strict_types=1);

namespace Drupal\Tests\dblog\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;

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
    $this->databaseDumpFiles = [
      DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the Watchdog view's permissions have been updated properly.
   */
  public function testPermissionUpdate(): void {
    // Load the Watchdog view.
    $watchdog_view = View::load('watchdog')->getExecutable();

    if ($watchdog_view instanceof ViewExecutable) {
      $current_perm = $watchdog_view
        ->getDisplay('default')
        ->getOption('access')['options']['perm'];

      // Check that the view's permission is 'access site reports'.
      $this->assertEquals('access site reports', $current_perm);
    }

    // Run the updates.
    $this->runUpdates();

    // Load the Watchdog view.
    $watchdog_view = View::load('watchdog')->getExecutable();

    if ($watchdog_view instanceof ViewExecutable) {
      $current_perm = $watchdog_view
        ->getDisplay('default')
        ->getOption('access')['options']['perm'];

      // Check that the view's permission is 'access dblog reports'.
      $this->assertEquals('access dblog reports', $current_perm);
    }
  }

}
