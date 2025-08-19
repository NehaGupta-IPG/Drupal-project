<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;

/**
 * Tests the addition of the default Entity Reference Filter `list_max` setting.
 *
 * @group Update
 * @covers views_post_update_entity_reference_list_max
 */
class EntityReferenceListMaxUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/test_entity_reference_list_max.php',
    ];
  }

  /**
   * Tests the upgrade path adding Entity Reference Filter `list_max` setting.
   */
  public function testViewsPostUpdateAddEntityReferenceListMax(): void {
    $view = View::load('test_filter_entity_reference_list_max_update');
    $display = $view->get('display');
    $entity_reference_counter = 0;
    foreach ($display['default']['display_options']['filters'] as $filter) {
      if ($filter['plugin_id'] === 'entity_reference') {
        $entity_reference_counter++;
      }
      $this->assertArrayNotHasKey('list_max', $filter);
    }
    $this->assertEquals(2, $entity_reference_counter);
    $this->runUpdates();

    $view = View::load('test_filter_entity_reference_list_max_update');
    $display = $view->get('display');
    $entity_reference_counter = 0;
    foreach ($display['default']['display_options']['filters'] as $filter) {
      if ($filter['plugin_id'] === 'entity_reference') {
        $entity_reference_counter++;
        $this->assertArrayHasKey('list_max', $filter);
        $this->assertEquals('100', $filter['list_max']);
      }
      else {
        $this->assertArrayNotHasKey('list_max', $filter);
      }
    }
    $this->assertEquals(2, $entity_reference_counter);
  }

}
