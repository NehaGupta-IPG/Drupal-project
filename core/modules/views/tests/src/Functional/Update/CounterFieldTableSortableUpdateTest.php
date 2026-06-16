<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests update path for table style result counter sorting.
 *
 * @see views_post_update_counter_field_table_sortable()
 */
#[Group('update')]
#[RunTestsInSeparateProcesses]
class CounterFieldTableSortableUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/table-sortable-counter.php',
    ];
  }

  /**
   * Tests existing table style result counter sorting is updated.
   */
  public function testCounterFieldTableSortableUpdate(): void {
    $views = View::loadMultiple();
    $data = $views['test_table_sortable_counter']->toArray();
    $non_table_style = $data['display']['page_1']['display_options']['style'];
    $this->assertSame('default', $non_table_style['type']);
    $this->assertTrue($data['display']['default']['display_options']['style']['options']['info']['counter']['sortable']);
    $this->assertTrue($data['display']['default']['display_options']['style']['options']['info']['counter_alias']['sortable']);
    $this->assertTrue($data['display']['default']['display_options']['style']['options']['info']['name']['sortable']);

    $this->runUpdates();

    $views = View::loadMultiple();
    $data = $views['test_table_sortable_counter']->toArray();
    $this->assertFalse($data['display']['default']['display_options']['style']['options']['info']['counter']['sortable']);
    $this->assertFalse($data['display']['default']['display_options']['style']['options']['info']['counter_alias']['sortable']);
    $this->assertTrue($data['display']['default']['display_options']['style']['options']['info']['name']['sortable']);
    $this->assertSame($non_table_style, $data['display']['page_1']['display_options']['style']);
  }

}
