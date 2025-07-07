<?php

namespace Drupal\Tests\field\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Tests that item.attributes are rendered on single-value fields.
 *
 * @group field
 */
class FieldItemAttributesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'system',
    'entity_test',
    'user',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install the schema and configs needed.
    $this->installEntitySchema('entity_test');
    $this->installConfig(['field']);
  }

  /**
   * Checking field attributes are rendering.
   */
  public function testItemAttributesAreRendered() {
    // Create a single-value text field on entity_test.
    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Test field',
    ])->save();

    // Create an entity with the field populated.
    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_test' => 'Hello World',
    ]);
    $entity->save();

    // Build the field render array manually.
    $view_builder = $this->container->get('entity_type.manager')->getViewBuilder('entity_test');
    $build = $view_builder->view($entity);

    // Inject item attributes manually (Option 2).
    $build['field_test'][0]['#item_attributes'] = [
      'class' => ['my-attribute-class'],
      'data-test' => 'value',
    ];

    // Render the field only.
    $output = (string) $this->container->get('renderer')->renderPlain($build['field_test']);

    // Check the rendered HTML includes the attributes.
    $this->assertStringContainsString('class="my-attribute-class"', $output);
    $this->assertStringContainsString('data-test="value"', $output);
    $this->assertStringContainsString('Hello World', $output);
  }

}
