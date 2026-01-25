<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;

/**
 * Tests the upgrade path for adding defaults for exposed form sort keys.
 *
 * @group Update
 *
 * @see views_post_update_views_exposed_form_sort_keys()
 */
class ExposedFormSortingKeysUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/entity-id-argument.php',
    ];
  }

  /**
   * Tests that numeric argument plugins are updated properly.
   */
  public function testViewsExposedFormSortingKeyDefaultsUpgrade(): void {
    $view = View::load('test_entity_id_argument_update');
    $data = $view->toArray();
    $this->assertArrayNotHasKey('expose_sort_key', $data['display']['default']['display_options']['exposed_form']['options']);
    $this->assertArrayNotHasKey('sort_order_key', $data['display']['default']['display_options']['exposed_form']['options']);

    $this->runUpdates();

    $view = View::load('test_entity_id_argument_update');
    $data = $view->toArray();
    $this->assertEquals('sort_by', $data['display']['default']['display_options']['exposed_form']['options']['expose_sort_key']);
    $this->assertEquals('sort_order', $data['display']['default']['display_options']['exposed_form']['options']['sort_order_key']);
  }

}
