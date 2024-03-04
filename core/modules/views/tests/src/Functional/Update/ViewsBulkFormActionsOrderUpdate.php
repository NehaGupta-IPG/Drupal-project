<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests views_post_update_bulk_form_action_order().
 *
 * @group views
 * @group legacy
 */
class ViewsBulkFormActionsOrderUpdate extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * @covers  \views_post_update_bulk_form_action_order
   */
  public function testBulkFormActionsOrderPostUpdate(): void {
    $config_factory = \Drupal::configFactory();
    $path = 'display.default.display_options.fields.user_bulk_form';
    $config = $config_factory->get('views.view.user_admin_people');
    $this->assertArrayNotHasKey('actions_order', $config->get($path));
    $this->runUpdates();
    $config = $config_factory->get('views.view.user_admin_people');
    $this->assertArrayHasKey('actions_order', $config->get($path));
    $this->assertSame([], $config->get("$path.actions_order"));
  }

}
