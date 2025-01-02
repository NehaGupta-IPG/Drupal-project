<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

/**
 * Tests original entity property accessed via Entity::getOriginal().
 *
 * @group Entity
 */
class EntityOriginalTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    foreach (entity_test_entity_types() as $entity_type_id) {
      // The entity_test schema is installed by the parent.
      if ($entity_type_id !== 'entity_test') {
        $this->installEntitySchema($entity_type_id);
      }
    }
  }

  /**
   * Tests original entity.
   */
  public function testOriginalEntity(): void {
    // All entity variations have to have the same results.
    foreach (entity_test_entity_types() as $entity_type) {
      $this->assertOriginalEntity($entity_type, 'entity_presave');
      $this->assertOriginalEntity($entity_type, 'entity_update');
    }
  }

  /**
   * Executes the original entity tests for the given entity type.
   *
   * @param string $entity_type
   *   The entity type to run the tests with.
   * @param string $hook
   *   The entity hook to run the tests with.
   *
   * @internal
   */
  protected function assertOriginalEntity(string $entity_type, string $hook): void {

    $global_var_name = 'entity_test_' . $hook . '_original_labels';

    $GLOBALS[$global_var_name] = [];

    $custom_entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'name' => 'Title 1',
      ]);
    $custom_entity->save();
    // The original entity is not set in the first save.
    $this->assertArrayNotHasKey($custom_entity->id(), $GLOBALS[$global_var_name]);

    // First update.
    $custom_entity->set('name', 'Title 2');
    $custom_entity->save();
    $this->assertEquals(
      'Title 1',
      end($GLOBALS[$global_var_name][$custom_entity->id()]),
    "Previous original label was correctly set in the original entity after the first update"
    );

    // Second update.
    $custom_entity->set('name', 'Title 3');
    $custom_entity->save();
    $this->assertEquals(
      'Title 2',
      end($GLOBALS[$global_var_name][$custom_entity->id()]),
      "Previous original label was correctly set in the original entity also after the second update"
    );
  }

}
