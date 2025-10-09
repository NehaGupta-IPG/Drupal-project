<?php

namespace Drupal\Tests\link\Kernel\Views;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests link views data.
 */
#[Group('link')]
class LinkViewsDataTest extends ViewsKernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'link',
    'views',
    'entity_test',
    'user',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_mul');
  }

  /**
   * Tests views data generated for link field relationship.
   *
   * @see \Drupal\link\Hook\LinkViewsHooks::fieldViewsDataAlter()
   */
  public function testClickSortable() {
    // Add a link field to entity_test.
    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_base_link',
      'type' => 'link',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_base_link',
      'bundle' => 'entity_test',
    ])->save();
    // Check the generated views data.
    $views_data = Views::viewsData()->get('entity_test__field_base_link');
    $fields = $views_data['field_base_link']['field'];
    $this->assertEquals('field', $fields['id']);
    $this->assertEquals('field_base_link', $fields['field_name']);
    $this->assertEquals(TRUE, $fields['click sortable']);

    // Add a link field to entity_test_mul.
    FieldStorageConfig::create([
      'entity_type' => 'entity_test_mul',
      'field_name' => 'field_data_link',
      'type' => 'link',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mul',
      'field_name' => 'field_data_link',
      'bundle' => 'entity_test_mul',
    ])->save();
    // Check the generated views data.
    $views_data = Views::viewsData()->get('entity_test_mul__field_data_link');
    $fields = $views_data['field_data_link']['field'];
    $this->assertEquals('field', $fields['id']);
    $this->assertEquals('field_data_link', $fields['field_name']);
    $this->assertEquals(TRUE, $fields['click sortable']);
  }

}
