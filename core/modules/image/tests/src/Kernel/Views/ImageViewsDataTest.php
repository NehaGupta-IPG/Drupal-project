<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel\Views;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests image views data.
 */
#[Group('image')]
#[RunTestsInSeparateProcesses]
class ImageViewsDataTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'image',
    'file',
    'entity_test',
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
   * Tests views data generated for image field relationship.
   *
   * @see image_field_views_data()
   * @see image_field_views_data_views_data_alter()
   */
  public function testRelationshipViewsData(): void {
    // Create image field to entity_test.
    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_base_image',
      'type' => 'image',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_base_image',
      'bundle' => 'entity_test',
    ])->save();
    // Check the generated views data.
    $views_data = Views::viewsData()->get('entity_test__field_base_image');
    $relationship = $views_data['field_base_image_target_id']['relationship'];
    $this->assertEquals('standard', $relationship['id']);
    $this->assertEquals('file_managed', $relationship['base']);
    $this->assertEquals('fid', $relationship['base field']);
    $this->assertEquals('file', $relationship['entity type']);

    $properties = [
      'width' => 'numeric',
      'height' => 'numeric',
      'alt' => 'standard',
      'title' => 'standard',
    ];
    foreach ($properties as $property => $type) {
      $field_data = $views_data['field_base_image_' . $property]['field'];
      $this->assertEquals($type, $field_data['id']);
      $this->assertEquals('field_base_image', $field_data['field_name']);
      $this->assertEquals('entity_test', $field_data['entity_type']);
      $this->assertEquals('field_base_image_' . $property, $field_data['real field']);
    }

    // Check the backwards reference.
    $views_data = Views::viewsData()->get('file_managed');
    $relationship = $views_data['reverse_field_base_image_entity_test']['relationship'];
    $this->assertEquals('entity_reverse', $relationship['id']);
    $this->assertEquals('entity_test', $relationship['base']);
    $this->assertEquals('id', $relationship['base field']);
    $this->assertEquals('entity_test__field_base_image', $relationship['field table']);
    $this->assertEquals('field_base_image_target_id', $relationship['field field']);
    $this->assertEquals('field_base_image', $relationship['field_name']);
    $this->assertEquals('entity_test', $relationship['entity_type']);
    $this->assertEquals(['field' => 'deleted', 'value' => 0, 'numeric' => TRUE], $relationship['join_extra'][0]);

    // Create image field to entity_test_mul.
    FieldStorageConfig::create([
      'entity_type' => 'entity_test_mul',
      'field_name' => 'field_data_image',
      'type' => 'image',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mul',
      'field_name' => 'field_data_image',
      'bundle' => 'entity_test_mul',
    ])->save();
    // Check the generated views data.
    $views_data = Views::viewsData()->get('entity_test_mul__field_data_image');
    $relationship = $views_data['field_data_image_target_id']['relationship'];
    $this->assertEquals('standard', $relationship['id']);
    $this->assertEquals('file_managed', $relationship['base']);
    $this->assertEquals('fid', $relationship['base field']);
    $this->assertEquals('file', $relationship['entity type']);

    $properties = [
      'width' => 'numeric',
      'height' => 'numeric',
      'alt' => 'standard',
      'title' => 'standard',
    ];
    foreach ($properties as $property => $type) {
      $field_data = $views_data['field_base_image_' . $property]['field'];
      $this->assertEquals($type, $field_data['id']);
      $this->assertEquals('field_base_image', $field_data['field_name']);
      $this->assertEquals('entity_test', $field_data['entity_type']);
      $this->assertEquals('field_base_image_' . $property, $field_data['real field']);
    }

    // Check the backwards reference.
    $views_data = Views::viewsData()->get('file_managed');
    $relationship = $views_data['reverse_field_data_image_entity_test_mul']['relationship'];
    $this->assertEquals('entity_reverse', $relationship['id']);
    $this->assertEquals('entity_test_mul_property_data', $relationship['base']);
    $this->assertEquals('id', $relationship['base field']);
    $this->assertEquals('entity_test_mul__field_data_image', $relationship['field table']);
    $this->assertEquals('field_data_image_target_id', $relationship['field field']);
    $this->assertEquals('field_data_image', $relationship['field_name']);
    $this->assertEquals('entity_test_mul', $relationship['entity_type']);
    $this->assertEquals(['field' => 'deleted', 'value' => 0, 'numeric' => TRUE], $relationship['join_extra'][0]);
  }

}
