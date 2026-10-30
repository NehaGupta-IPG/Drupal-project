<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Entity\EntityDefinitionUpdateManager;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests converting an entity type that never had a langcode key.
 *
 * FieldableEntityDefinitionUpdateTest converts an entity type that carries a
 * langcode key from the start, so its base table always has a langcode column
 * to fall back on. An entity type that was never translatable has neither,
 * and that is what makes the difference: the storage keeps a stale langcode
 * key while the existing rows are read, and the read joins the dedicated
 * field tables on a column the original base table does not have.
 */
#[CoversClass(EntityDefinitionUpdateManager::class)]
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class FieldableEntityDefinitionUpdateNoLangcodeKeyTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test_update', 'language'];

  /**
   * The ID of the entity type used in this test.
   */
  protected string $entityTypeId = 'entity_test_update';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setEntityType(function ($entity_type): void {
      $keys = $entity_type->getKeys();
      unset($keys['langcode']);
      $entity_type->set('entity_keys', $keys);
    });

    $this->installEntitySchema($this->entityTypeId);
    ConfigurableLanguage::createFromLangcode('ro')->save();

    $this->assertFalse(
      $this->container->get('database')->schema()->fieldExists($this->entityTypeId, 'langcode'),
      'The entity type starts out without a langcode column.',
    );
  }

  /**
   * Alters the test entity type definition and rebuilds the caches.
   *
   * @param callable $alter
   *   Receives the entity type to change in place.
   */
  protected function setEntityType(callable $alter): void {
    $entity_type = clone $this->container->get('entity_type.manager')->getDefinition($this->entityTypeId);
    $alter($entity_type);

    $this->container->get('state')->set('entity_test_update.entity_type', $entity_type);
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * A multi-value base field survives the conversion to translatable.
   */
  public function testConversionToTranslatable(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage($this->entityTypeId);
    for ($i = 1; $i <= 3; $i++) {
      $storage->create([
        'id' => $i,
        'type' => 'test_bundle',
        'name' => 'test entity - ' . $i,
        // Cardinality 2, so this one lives in a dedicated table - which is
        // where the langcode join happens.
        'test_multiple_properties_multiple_values' => [
          ['value1' => 'delta 0 - value 1 - ' . $i, 'value2' => 'delta 0 - value 2 - ' . $i],
          ['value1' => 'delta 1 - value 1 - ' . $i, 'value2' => 'delta 1 - value 2 - ' . $i],
        ],
      ])->save();
    }

    // Make the entity type translatable, the way a module's hook_update_N
    // does: the langcode key arrives together with the translatable flag.
    $this->setEntityType(function ($entity_type): void {
      $keys = $entity_type->getKeys();
      $keys['langcode'] = 'langcode';
      $entity_type->set('entity_keys', $keys);
      $entity_type->set('translatable', TRUE);
      $entity_type->set('data_table', $this->entityTypeId . '_data');
    });

    $entity_type = $this->container->get('entity_type.manager')->getDefinition($this->entityTypeId);
    $field_storage_definitions = $this->container->get('entity_field.manager')
      ->getFieldStorageDefinitions($this->entityTypeId);

    $sandbox = [];
    do {
      $this->container->get('entity.definition_update_manager')
        ->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);
    } while (($sandbox['#finished'] ?? 1) < 1);

    // The rows came across, and so did the values of the multi-value field.
    // Both matter: the join that fails here can also match nothing, which
    // empties the field without anyone noticing.
    $storage = $this->container->get('entity_type.manager')->getStorage($this->entityTypeId);
    $storage->resetCache();
    $entities = $storage->loadMultiple();
    $this->assertCount(3, $entities);

    foreach ($entities as $id => $entity) {
      $this->assertSame('test entity - ' . $id, $entity->get('name')->value);
      $values = $entity->get('test_multiple_properties_multiple_values')->getValue();
      $this->assertSame([
        ['value1' => 'delta 0 - value 1 - ' . $id, 'value2' => 'delta 0 - value 2 - ' . $id],
        ['value1' => 'delta 1 - value 1 - ' . $id, 'value2' => 'delta 1 - value 2 - ' . $id],
      ], $values, 'The values of the multi-value base field survived the conversion.');
    }
  }

}
