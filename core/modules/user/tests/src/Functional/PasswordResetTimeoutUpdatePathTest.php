<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for the password_reset_timeout minimum value works correctly.
 *
 * @group Update
 */
class PasswordResetTimeoutUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade path for setting the minimum password_reset_timeout value.
   */
  public function testRunUpdates(): void {
    $config = \Drupal::configFactory()->getEditable('user.settings');
    $config->set('password_reset_timeout', 1);
    $config->save();
    $this->assertEquals(1, $config->get('password_reset_timeout'));

    $this->runUpdates();

    $config = \Drupal::config('user.settings');
    $this->assertEquals(300, $config->get('password_reset_timeout'));
  }

}
