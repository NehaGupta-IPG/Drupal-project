<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests user_post_update_configure_cancel_options() upgrade path.
 *
 * @group Update
 * @group legacy
 */
class UserUpdateCancelOptionsTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles[] = __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz';
  }

  /**
   * Tests that cancel methods are set correctly on user.settings.
   */
  public function testRolePermissions(): void {
    $cancel_methods = $this->config('user.settings')->get('cancel_method_options');
    $this->assertNull($cancel_methods);

    // Run updates.
    $this->runUpdates();

    $config = $this->config('user.settings');
    $cancel_methods = $config->get('cancel_method_options');

    $expected_cancel_methods = [
      'user_cancel_block' => TRUE,
      'user_cancel_block_unpublish' => TRUE,
      'user_cancel_reassign' => TRUE,
      'user_cancel_delete' => TRUE,
    ];

    $this->assertEquals($expected_cancel_methods, $cancel_methods);

    // Check valid Schema after update.
    $this->assertConfigSchema(\Drupal::service('config.typed'), 'user.settings', $config->get());
  }

}
