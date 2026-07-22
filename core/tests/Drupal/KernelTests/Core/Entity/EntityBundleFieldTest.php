<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Field\FieldPurger;
use Drupal\entity_test\EntityTestHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests adding a custom bundle field.
 */
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class EntityBundleFieldTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_schema_test', 'entity_test_update'];

  /**
   * The database connection used.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('entity_test_update');
    $this->database = $this->container->get('database');
  }

  /**
   * Tests making use of a custom bundle field.
   */
  public function testCustomBundleFieldUsage(): void {
    $is_json_storage = $this->database->driver() == 'pgsql';
    EntityTestHelper::createBundle('custom', NULL, 'entity_test_update');

    // Check that an entity with bundle entity_test does not have the custom
    // field.
    $storage = $this->entityTypeManager->getStorage('entity_test_update');
    $entity = $storage->create([
      'type' => 'entity_test_update',
    ]);
    $this->assertFalse($entity->hasField('custom_bundle_field'));

    // Check that the custom bundle has the defined custom field and check
    // saving and deleting of custom field data.
    $entity = $storage->create([
      'type' => 'custom',
    ]);
    $this->assertTrue($entity->hasField('custom_bundle_field'));

    // Ensure that the field exists in the field map.
    $field_map = \Drupal::service('entity_field.manager')->getFieldMap();
    $this->assertEquals(['type' => 'string', 'bundles' => ['custom' => 'custom']], $field_map['entity_test_update']['custom_bundle_field']);
    $entity->custom_bundle_field->value = 'swanky';
    $entity->save();
    $storage->resetCache();
    $entity = $storage->load($entity->id());
    $this->assertEquals('swanky', $entity->custom_bundle_field->value, 'Entity was saved correctly');

    $entity->custom_bundle_field->value = 'cozy';
    $entity->save();
    $storage->resetCache();
    $entity = $storage->load($entity->id());
    $this->assertEquals('cozy', $entity->custom_bundle_field->value, 'Entity was updated correctly.');

    $deleted_entity_id = $entity->id();
    $entity->delete();
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $storage->getTableMapping();
    $table = $table_mapping->getDedicatedDataTableName($entity->getFieldDefinition('custom_bundle_field')->getFieldStorageDefinition());
    if ($is_json_storage) {
      // The pgsql driver embeds the dedicated field data in the base row's
      // jsonb column, so deleting the entity removes the field data with it;
      // there is no physical dedicated table to read.
      $storage->resetCache();
      $this->assertNull($storage->load($deleted_entity_id), 'Field data has been deleted');
    }
    else {
      $result = $this->database->select($table, 'f')
        ->fields('f')
        ->condition('f.entity_id', $entity->id())
        ->execute();
      $this->assertFalse($result->fetchAssoc(), 'Field data has been deleted');
    }

    // Create another entity to test that values are marked as deleted when a
    // bundle is deleted.
    $entity = $storage->create(['type' => 'custom', 'custom_bundle_field' => 'new']);
    $entity->save();
    EntityTestHelper::deleteBundle('custom', 'entity_test_update');

    if ($is_json_storage) {
      // Deleting the bundle marks the embedded field data deleted; assert the
      // matching base row now carries a deleted-flagged row under the field's
      // context-qualified "deleted" dedicated-table key in its jsonb column.
      $table = $table_mapping->getJsonStorageDedicatedTableName($entity->getFieldDefinition('custom_bundle_field')->getFieldStorageDefinition(), $table_mapping->getBaseTable(), TRUE);
      $raw = $this->database->select('entity_test_update', 't')
        ->fields('t', ['__data'])
        ->condition('id', $entity->id())
        ->execute()
        ->fetchField();
      $document = $raw ? json_decode($raw, TRUE) : [];
      $deleted_rows = array_filter($document[$table] ?? [], static fn($row) => !empty($row['deleted']));
      $this->assertCount(1, $deleted_rows, 'Field data has been deleted');
    }
    else {
      $table = $table_mapping->getDedicatedDataTableName($entity->getFieldDefinition('custom_bundle_field')->getFieldStorageDefinition(), TRUE);
      $result = $this->database->select($table, 'f')
        ->condition('f.entity_id', $entity->id())
        ->condition('deleted', 1)
        ->countQuery()
        ->execute();
      $this->assertEquals(1, $result->fetchField(), 'Field data has been deleted');
    }

    // Ensure that the field no longer exists in the field map.
    $field_map = \Drupal::service('entity_field.manager')->getFieldMap();
    $this->assertFalse(isset($field_map['entity_test_update']['custom_bundle_field']));

    // Purge field data, and check that the storage definition has been
    // completely removed once the data is purged.
    \Drupal::service(FieldPurger::class)->purgeBatch(10);
    $this->assertFalse($this->database->schema()->tableExists($table), 'Custom field table was deleted');
  }

}
