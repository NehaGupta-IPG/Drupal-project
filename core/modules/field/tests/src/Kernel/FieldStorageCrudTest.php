<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\Exception\FieldStorageDefinitionUpdateForbiddenException;
use Drupal\Core\Field\FieldException;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_test\FieldTestHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests field storage create, read, update, and delete.
 */
#[Group('field')]
#[RunTestsInSeparateProcesses]
class FieldStorageCrudTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  // @todo Test creation with
  // - a full fledged $field structure, check that all the values are there
  // - a minimal $field structure, check all default values are set
  // defer actual $field comparison to a helper function, used for the two cases above.

  /**
   * Tests the creation of a field storage.
   */
  public function testCreate(): void {
    $field_storage_definition = [
      'field_name' => 'field_2',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
    ];
    FieldTestHelper::memorize();
    $field_storage = FieldStorageConfig::create($field_storage_definition);
    $field_storage->save();

    $field_storage = FieldStorageConfig::load($field_storage->id());
    $this->assertEquals('TRUE', $field_storage->getSetting('storage_setting_from_config_data'));
    $this->assertNull($field_storage->getSetting('config_data_from_storage_setting'));

    $mem = FieldTestHelper::memorize();
    $this->assertSame($field_storage_definition['field_name'], $mem['Drupal\field_test\Hook\FieldTestHooks::fieldStorageConfigCreate'][0][0]->getName(), 'hook_entity_create() called with correct arguments.');
    $this->assertSame($field_storage_definition['type'], $mem['Drupal\field_test\Hook\FieldTestHooks::fieldStorageConfigCreate'][0][0]->getType(), 'hook_entity_create() called with correct arguments.');

    // Read the configuration. Check against raw configuration data rather than
    // the loaded ConfigEntity, to be sure we check that the defaults are
    // applied on write.
    $field_storage_config = $this->config('field.storage.' . $field_storage->id())->get();

    $this->assertTrue($field_storage_config['settings']['config_data_from_storage_setting']);
    $this->assertTrue(!isset($field_storage_config['settings']['storage_setting_from_config_data']));

    // Since we are working with raw configuration, this needs to be unset
    // manually.
    // @see Drupal\field_test\Plugin\Field\FieldType\TestItem::storageSettingsFromConfigData()
    unset($field_storage_config['settings']['config_data_from_storage_setting']);

    // Ensure that basic properties are preserved.
    $this->assertEquals($field_storage_definition['field_name'], $field_storage_config['field_name'], 'The field name is properly saved.');
    $this->assertEquals($field_storage_definition['entity_type'], $field_storage_config['entity_type'], 'The field entity type is properly saved.');
    $this->assertEquals($field_storage_definition['entity_type'] . '.' . $field_storage_definition['field_name'], $field_storage_config['id'], 'The field id is properly saved.');
    $this->assertEquals($field_storage_definition['type'], $field_storage_config['type'], 'The field type is properly saved.');

    // Ensure that cardinality defaults to 1.
    $this->assertEquals(1, $field_storage_config['cardinality'], 'Cardinality defaults to 1.');

    // Ensure that default settings are present.
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    $this->assertEquals($field_type_manager->getDefaultStorageSettings($field_storage_definition['type']), $field_storage_config['settings'], 'Default storage settings have been written.');

    // Guarantee that the name is unique.
    try {
      FieldStorageConfig::create($field_storage_definition)->save();
      $this->fail('Cannot create two fields with the same name.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(EntityStorageException::class, $e);
    }

    // Check that field type is required.
    try {
      $field_storage_definition = [
        'field_name' => 'field_1',
        'entity_type' => 'entity_type',
      ];
      FieldStorageConfig::create($field_storage_definition)->save();
      $this->fail('Cannot create a field with no type.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(FieldException::class, $e);
    }

    // Check that field name is required.
    try {
      $field_storage_definition = [
        'type' => 'test_field',
        'entity_type' => 'entity_test',
      ];
      FieldStorageConfig::create($field_storage_definition)->save();
      $this->fail('Cannot create an unnamed field.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(FieldException::class, $e);
    }
    // Check that entity type is required.
    try {
      $field_storage_definition = [
        'field_name' => 'test_field',
        'type' => 'test_field',
      ];
      FieldStorageConfig::create($field_storage_definition)->save();
      $this->fail('Cannot create a field without an entity type.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(FieldException::class, $e);
    }

    // Check that field name must start with a letter or _.
    try {
      $field_storage_definition = [
        'field_name' => '2field_2',
        'entity_type' => 'entity_test',
        'type' => 'test_field',
      ];
      FieldStorageConfig::create($field_storage_definition)->save();
      $this->fail('Cannot create a field with a name starting with a digit.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(FieldException::class, $e);
    }

    // Check that field name must only contain lowercase alphanumeric or _.
    try {
      $field_storage_definition = [
        'field_name' => 'field#_3',
        'entity_type' => 'entity_test',
        'type' => 'test_field',
      ];
      FieldStorageConfig::create($field_storage_definition)->save();
      $this->fail('Cannot create a field with a name containing an invalid character.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(FieldException::class, $e);
    }

    // Check that field name cannot be longer than 32 characters long.
    try {
      $field_storage_definition = [
        'field_name' => '_12345678901234567890123456789012',
        'entity_type' => 'entity_test',
        'type' => 'test_field',
      ];
      FieldStorageConfig::create($field_storage_definition)->save();
      $this->fail('Cannot create a field with a name longer than 32 characters.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(FieldException::class, $e);
    }

    // Check that field name can not be an entity key.
    // "id" is known as an entity key from the "entity_test" type.
    try {
      $field_storage_definition = [
        'type' => 'test_field',
        'field_name' => 'id',
        'entity_type' => 'entity_test',
      ];
      FieldStorageConfig::create($field_storage_definition)->save();
      $this->fail('Cannot create a field bearing the name of an entity key.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(FieldException::class, $e);
    }
  }

  /**
   * Tests that an explicit schema can be provided on creation.
   *
   * This behavior is needed to allow field storage creation within updates,
   * since plugin classes (and thus the field type schema) cannot be accessed.
   */
  public function testCreateWithExplicitSchema(): void {
    $schema = [
      'dummy' => 'foobar',
    ];
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_2',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
      'schema' => $schema,
    ]);
    $this->assertEquals($schema, $field_storage->getSchema());
  }

  /**
   * Tests reading field storage definitions.
   */
  public function testRead(): void {
    $field_storage_definition = [
      'field_name' => 'field_1',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
    ];
    $field_storage = FieldStorageConfig::create($field_storage_definition);
    $field_storage->save();
    $id = $field_storage->id();

    // Check that 'single column' criteria works.
    $field_storage_config_storage = \Drupal::entityTypeManager()->getStorage('field_storage_config');
    $fields = $field_storage_config_storage->loadByProperties(['field_name' => $field_storage_definition['field_name']]);
    $this->assertCount(1, $fields, 'The field was properly read.');
    $this->assertArrayHasKey($id, $fields, 'The field has the correct key.');

    // Check that 'multi column' criteria works.
    $fields = $field_storage_config_storage->loadByProperties([
      'field_name' => $field_storage_definition['field_name'],
      'type' => $field_storage_definition['type'],
      'entity_type' => $field_storage_definition['entity_type'],
    ]);
    $this->assertCount(1, $fields, 'The field was properly read.');
    $this->assertArrayHasKey($id, $fields, 'The field has the correct key.');
    $fields = $field_storage_config_storage->loadByProperties([
      'field_name' => $field_storage_definition['field_name'],
      'type' => 'foo',
    ]);
    $this->assertEmpty($fields, 'No field was found.');

    // Create a field from the field storage.
    $field_definition = [
      'field_name' => $field_storage_definition['field_name'],
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ];
    FieldConfig::create($field_definition)->save();
  }

  /**
   * Tests creation of indexes on data column.
   */
  public function testIndexes(): void {
    // Check that indexes specified by the field type are used by default.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_1',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
    ]);
    $field_storage->save();
    $field_storage = FieldStorageConfig::load($field_storage->id());
    $schema = $field_storage->getSchema();
    $expected_indexes = ['value' => ['value']];
    $this->assertEquals($expected_indexes, $schema['indexes'], 'Field type indexes saved by default');

    // Check that indexes specified by the field definition override the field
    // type indexes.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_2',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
      'indexes' => [
        'value' => [],
      ],
    ]);
    $field_storage->save();
    $field_storage = FieldStorageConfig::load($field_storage->id());
    $schema = $field_storage->getSchema();
    $expected_indexes = ['value' => []];
    $this->assertEquals($expected_indexes, $schema['indexes'], 'Field definition indexes override field type indexes');

    // Check that indexes specified by the field definition add to the field
    // type indexes.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_3',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
      'indexes' => [
        'value_2' => ['value'],
      ],
    ]);
    $field_storage->save();
    $id = $field_storage->id();
    $field_storage = FieldStorageConfig::load($id);
    $schema = $field_storage->getSchema();
    $expected_indexes = ['value' => ['value'], 'value_2' => ['value']];
    $this->assertEquals($expected_indexes, $schema['indexes'], 'Field definition indexes are merged with field type indexes');
  }

  /**
   * Tests the deletion of a field storage.
   */
  public function testDeleteNoData(): void {
    // Deleting and purging field storages with data is tested in
    // \Drupal\Tests\field\Kernel\BulkDeleteTest.

    // Create two fields (so we can test that only one is deleted).
    $field_storage_definition = [
      'field_name' => 'field_1',
      'type' => 'test_field',
      'entity_type' => 'entity_test',
    ];
    FieldStorageConfig::create($field_storage_definition)->save();
    $another_field_storage_definition = [
      'field_name' => 'field_2',
      'type' => 'test_field',
      'entity_type' => 'entity_test',
    ];
    FieldStorageConfig::create($another_field_storage_definition)->save();

    // Create fields for each.
    $field_definition = [
      'field_name' => $field_storage_definition['field_name'],
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ];
    FieldConfig::create($field_definition)->save();
    $another_field_definition = $field_definition;
    $another_field_definition['field_name'] = $another_field_storage_definition['field_name'];
    FieldConfig::create($another_field_definition)->save();

    // Test that the first field is not deleted, and then delete it.
    $field_storage_config_storage = \Drupal::entityTypeManager()->getStorage('field_storage_config');
    $field_storage = current($field_storage_config_storage->loadByProperties([
      'field_name' => $field_storage_definition['field_name'],
      'include_deleted' => TRUE,
    ]));
    $this->assertFalse($field_storage->isDeleted());
    FieldStorageConfig::loadByName('entity_test', $field_storage_definition['field_name'])->delete();

    // Make sure that the field storage is deleted as it had no data.
    $field_storages = $field_storage_config_storage->loadByProperties([
      'field_name' => $field_storage_definition['field_name'],
      'include_deleted' => TRUE,
    ]);
    $this->assertCount(0, $field_storages, 'Field storage was deleted');

    // Make sure that this field is marked as deleted when it is
    // specifically loaded.
    $fields = \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->loadByProperties([
        'entity_type' => 'entity_test',
        'field_name' => $field_definition['field_name'],
        'bundle' => $field_definition['bundle'],
        'include_deleted' => TRUE,
      ]);
    $this->assertCount(0, $fields, 'Field storage was deleted');

    // Try to load the storage normally and make sure it does not show up.
    $field_storage = FieldStorageConfig::load('entity_test.' . $field_storage_definition['field_name']);
    $this->assertEmpty($field_storage, 'Field storage was deleted');

    // Try to load the field normally and make sure it does not show up.
    $field = FieldConfig::load('entity_test.' . $field_definition['bundle'] . '.' . $field_definition['field_name']);
    $this->assertEmpty($field, 'Field was deleted');

    // Make sure the other field and its storage are not deleted.
    $another_field_storage = FieldStorageConfig::load('entity_test.' . $another_field_storage_definition['field_name']);
    $this->assertFalse($another_field_storage->isDeleted());
    $another_field = FieldConfig::load('entity_test.' . $another_field_definition['bundle'] . '.' . $another_field_definition['field_name']);
    $this->assertFalse($another_field->isDeleted());

    // Try to create a new field the same name as a deleted field and
    // write data into it.
    FieldStorageConfig::create($field_storage_definition)->save();
    FieldConfig::create($field_definition)->save();
    $field_storage = FieldStorageConfig::load('entity_test.' . $field_storage_definition['field_name']);
    $this->assertFalse($field_storage->isDeleted());
    $field = FieldConfig::load('entity_test.' . $field_definition['bundle'] . '.' . $field_definition['field_name']);
    $this->assertFalse($field->isDeleted());

    // Save an entity with data for the field.
    $entity = EntityTest::create();
    $values[0]['value'] = mt_rand(1, 127);
    $entity->{$field_storage->getName()}->value = $values[0]['value'];
    $entity = $this->entitySaveReload($entity);

    // Verify the field is present on load.
    $this->assertCount(1, $entity->{$field_storage->getName()}, "Data in previously deleted field saves and loads correctly");
    foreach ($values as $delta => $value) {
      $this->assertEquals($values[$delta]['value'], $entity->{$field_storage->getName()}[$delta]->value, "Data in previously deleted field saves and loads correctly");
    }
  }

  /**
   * Tests that updating a field storage type is not allowed.
   */
  public function testUpdateFieldType(): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_type',
      'entity_type' => 'entity_test',
      'type' => 'decimal',
    ]);
    $field_storage->save();

    try {
      $field_storage->set('type', 'integer');
      $field_storage->save();
      $this->fail('Cannot update a field to a different type.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(FieldException::class, $e);
    }
  }

  /**
   * Tests changing a field storage type.
   */
  public function testUpdateEntityType(): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_type',
      'entity_type' => 'entity_test',
      'type' => 'decimal',
    ]);
    $field_storage->save();

    $this->expectException(FieldException::class);
    $this->expectExceptionMessageIs('Cannot change the field type for an existing field storage. The field storage entity_test.field_type has the type decimal.');

    $field_storage->set('type', 'foobar');
    $field_storage->save();
  }

  /**
   * Tests changing a field storage entity type.
   */
  public function testUpdateEntityTargetType(): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_type',
      'entity_type' => 'entity_test',
      'type' => 'decimal',
    ]);
    $field_storage->save();

    $this->expectException(FieldException::class);
    $this->expectExceptionMessageIs('Cannot change the entity type for an existing field storage. The field storage foobar.field_type has the type entity_test.');

    $field_storage->set('entity_type', 'foobar');
    $field_storage->save();
  }

  /**
   * Tests updating a field storage.
   */
  public function testUpdate(): void {
    // Create a field with a defined cardinality, so that we can ensure it's
    // respected. Since cardinality enforcement is consistent across database
    // systems, it makes a good test case.
    $cardinality = 4;
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_update',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
      'cardinality' => $cardinality,
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ]);
    $field->save();

    do {
      $entity = EntityTest::create();
      // Fill in the entity with more values than $cardinality.
      for ($i = 0; $i < 20; $i++) {
        // We can not use $i here because 0 values are filtered out.
        $entity->field_update[] = $i + 1;
      }
      // Load back and assert there are $cardinality number of values.
      $entity = $this->entitySaveReload($entity);
      $this->assertCount($field_storage->getCardinality(), $entity->field_update);
      // Now check the values themselves.
      for ($delta = 0; $delta < $cardinality; $delta++) {
        $this->assertEquals($delta + 1, $entity->field_update[$delta]->value);
      }
      // Increase $cardinality and set the field cardinality to the new value.
      $field_storage->setCardinality(++$cardinality);
      $field_storage->save();
    } while ($cardinality < 6);
  }

  /**
   * Tests adding an index to a dedicated-table field storage that has data.
   *
   * The entity_test entity type is not revisionable, so its dedicated field
   * tables have no revision counterpart. Updating the indexes of a field
   * storage that already has data goes through
   * SqlContentEntityStorageSchema::updateDedicatedTableSchema(), which must
   * not assume a revision table exists for every entity type.
   *
   * @see https://www.drupal.org/project/drupal/issues/3305627
   */
  public function testUpdateIndexesWithDataNonRevisionable(): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_update_indexes',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
      // Force dedicated-table storage.
      'cardinality' => 2,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    // Save an entity so the field's dedicated table has data. This is what
    // sends FieldStorageConfig::preSave() down the "has data" branch of
    // updateDedicatedTableSchema() when the storage is updated below.
    $entity = EntityTest::create();
    $entity->field_update_indexes = 1;
    $entity->save();

    // Add a second index. Prior to the fix for #3305627, this throws
    // a fatal TypeError because updateDedicatedTableSchema() unconditionally
    // reads $actual_schema[$revision_table], which does not exist for a
    // non-revisionable entity type.
    $field_storage->setIndexes([
      'value' => ['value'],
      'value_2' => ['value'],
    ]);
    $field_storage->save();

    $table_mapping = \Drupal::entityTypeManager()->getStorage('entity_test')->getTableMapping();
    $table = $table_mapping->getDedicatedDataTableName($field_storage);
    $this->assertTrue(
      \Drupal::database()->schema()->indexExists($table, 'field_update_indexes_value_2'),
      'The new index was created on the dedicated field table.'
    );

    // Removing the index again must likewise not throw.
    $field_storage->setIndexes([
      'value' => ['value'],
    ]);
    $field_storage->save();
    $this->assertFalse(
      \Drupal::database()->schema()->indexExists($table, 'field_update_indexes_value_2'),
      'The removed index no longer exists on the dedicated field table.'
    );
  }

  /**
   * Tests adding/removing an index on a revisionable entity's dedicated table.
   *
   * This is the counterpart to testUpdateIndexesWithDataNonRevisionable():
   * that test alone would pass even if the revision-table index operations
   * were skipped unconditionally, since it never has a revision table to
   * update. This test is what actually exercises and pins down the "index
   * still gets created on/removed from the revision table when one exists"
   * behavior, so a future change cannot silently drop that maintenance
   * without a test catching it.
   *
   * @see https://www.drupal.org/project/drupal/issues/3305627
   */
  public function testUpdateIndexesWithDataRevisionable(): void {
    $this->installEntitySchema('entity_test_rev');

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_update_indexes',
      'entity_type' => 'entity_test_rev',
      'type' => 'test_field',
      'cardinality' => 2,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type' => 'entity_test_rev',
      'bundle' => 'entity_test_rev',
    ])->save();

    $entity = EntityTestRev::create();
    $entity->field_update_indexes = 1;
    $entity->save();

    $table_mapping = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->getTableMapping();
    $table = $table_mapping->getDedicatedDataTableName($field_storage);
    $revision_table = $table_mapping->getDedicatedRevisionTableName($field_storage);
    $schema = \Drupal::database()->schema();

    // Add a second index. This must be created on both the data table and
    // the revision table.
    $field_storage->setIndexes([
      'value' => ['value'],
      'value_2' => ['value'],
    ]);
    $field_storage->save();
    $this->assertTrue(
      $schema->indexExists($table, 'field_update_indexes_value_2'),
      'The new index was created on the dedicated field data table.'
    );
    $this->assertTrue(
      $schema->indexExists($revision_table, 'field_update_indexes_value_2'),
      'The new index was created on the dedicated field revision table.'
    );

    // Removing the index must remove it from both tables too.
    $field_storage->setIndexes([
      'value' => ['value'],
    ]);
    $field_storage->save();
    $this->assertFalse(
      $schema->indexExists($table, 'field_update_indexes_value_2'),
      'The removed index no longer exists on the dedicated field data table.'
    );
    $this->assertFalse(
      $schema->indexExists($revision_table, 'field_update_indexes_value_2'),
      'The removed index no longer exists on the dedicated field revision table.'
    );
  }

  /**
   * Tests indexing a dedicated table's own reserved columns while it has data.
   *
   * Dedicated field tables always have bundle, deleted, entity_id,
   * revision_id, and langcode columns in addition to the field type's own
   * value column(s). Prior to the fix for #3305627,
   * DefaultTableMapping::getFieldColumnName() only recognized "deleted" as
   * such a reserved column, so an index referencing e.g. "entity_id" was
   * incorrectly resolved to a prefixed, non-existent column name such as
   * "field_update_indexes_entity_id".
   *
   * @see https://www.drupal.org/project/drupal/issues/3305627
   */
  public function testUpdateIndexOnReservedColumnWithData(): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_update_indexes',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
      'cardinality' => 2,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    $entity = EntityTest::create();
    $entity->field_update_indexes = 1;
    $entity->save();

    // Add an index over the field's own value column and the dedicated
    // table's built-in entity_id column. This must resolve "entity_id" to
    // the real, unprefixed column rather than throwing a database exception
    // for a non-existent "field_update_indexes_entity_id" column.
    $field_storage->setIndexes([
      'value' => ['value'],
      'value_entity_id' => ['value', 'entity_id'],
    ]);
    $field_storage->save();

    $table_mapping = \Drupal::entityTypeManager()->getStorage('entity_test')->getTableMapping();
    $table = $table_mapping->getDedicatedDataTableName($field_storage);
    $this->assertTrue(
      \Drupal::database()->schema()->indexExists($table, 'field_update_indexes_value_entity_id'),
      'The new index over the reserved entity_id column was created on the dedicated field table.'
    );
  }

  /**
   * Tests indexing a field type with more than one value property.
   *
   * All the other index tests in this class use test_field, which has a
   * single "value" property, so getFieldColumnName() only ever has to
   * disambiguate one field-owned column from the dedicated table's built-in
   * columns. This uses "daterange" (value and end_value) to confirm that
   * column resolution also works correctly when an index spans more than
   * one of the field's own columns, together with one of the dedicated
   * table's reserved columns.
   *
   * @see https://www.drupal.org/project/drupal/issues/3305627
   */
  public function testUpdateIndexOnMultiColumnFieldWithData(): void {
    $this->enableModules(['datetime', 'datetime_range']);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_update_daterange',
      'entity_type' => 'entity_test',
      'type' => 'daterange',
      'cardinality' => 2,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    $entity = EntityTest::create();
    $entity->field_update_daterange = [
      'value' => '2016-09-21',
      'end_value' => '2016-09-25',
    ];
    $entity->save();

    // Add an index spanning both of the field's own columns (value and
    // end_value) plus the dedicated table's built-in entity_id column.
    $field_storage->setIndexes([
      'value' => ['value'],
      'range_entity_id' => ['value', 'end_value', 'entity_id'],
    ]);
    $field_storage->save();

    $table_mapping = \Drupal::entityTypeManager()->getStorage('entity_test')->getTableMapping();
    $table = $table_mapping->getDedicatedDataTableName($field_storage);
    $this->assertTrue(
      \Drupal::database()->schema()->indexExists($table, 'field_update_daterange_range_entity_id'),
      'The new multi-column index was created on the dedicated field table.'
    );

    // Removing it must likewise not throw.
    $field_storage->setIndexes([
      'value' => ['value'],
    ]);
    $field_storage->save();
    $this->assertFalse(
      \Drupal::database()->schema()->indexExists($table, 'field_update_daterange_range_entity_id'),
      'The removed multi-column index no longer exists on the dedicated field table.'
    );
  }

  /**
   * Tests a field type whose own property name collides with a reserved one.
   *
   * A dedicated field table has its own built-in "langcode" column, but some
   * field types have their own property that happens to be called
   * "langcode" too (e.g. the contrib "address" field type). A field's own
   * column must always win that naming collision: it needs to keep
   * resolving to its normal, prefixed column, not the table's built-in one,
   * or the field's data silently collides with (and is overwritten by) the
   * table's own langcode column.
   *
   * Uses field_test's test_field_reserved_column field type, which defines
   * a "langcode" property for exactly this purpose.
   *
   * The "own_langcode" index below is also this class's proof that a
   * collision like this permanently forfeits the ability to index the
   * table's own reserved column under that name: there is no way to write
   * an index that reaches the table's built-in langcode column once a
   * field type has claimed that name for itself. That's a known, accepted
   * limitation rather than a bug - see the DEDICATED_TABLE_COLUMNS docblock
   * on DefaultTableMapping for why it isn't (and shouldn't be) resolved
   * here.
   *
   * @see \Drupal\Core\Entity\Sql\DefaultTableMapping::DEDICATED_TABLE_COLUMNS
   * @see https://www.drupal.org/project/drupal/issues/3305627
   */
  public function testFieldOwnColumnTakesPrecedenceOverReservedColumn(): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_reserved_lang',
      'entity_type' => 'entity_test',
      'type' => 'test_field_reserved_column',
      'cardinality' => 2,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    $table_mapping = \Drupal::entityTypeManager()->getStorage('entity_test')->getTableMapping();

    // The field's own "langcode" property must resolve to its normal
    // prefixed column, exactly as it would for any other property name.
    $this->assertSame(
      'field_reserved_lang_langcode',
      $table_mapping->getFieldColumnName($field_storage, 'langcode'),
      "The field's own langcode property is not resolved to the dedicated table's built-in langcode column."
    );

    // Save an entity with distinct values in the field's own "langcode"
    // property and verify, by reading the raw table row, that it landed in
    // the field's own column rather than being collapsed into (or
    // overwriting) the table's built-in langcode column.
    $entity = EntityTest::create();
    $entity->field_reserved_lang = ['value' => 1, 'langcode' => 'xx'];
    $entity->save();

    $table = $table_mapping->getDedicatedDataTableName($field_storage);
    $row = \Drupal::database()->select($table, 't')
      ->fields('t')
      ->condition('entity_id', $entity->id())
      ->execute()
      ->fetchAssoc();
    $this->assertSame('xx', $row['field_reserved_lang_langcode'], "The field's own langcode value was stored in its own column.");
    $this->assertNotSame('xx', $row['langcode'], "The table's own langcode column was not overwritten by the field's own langcode value.");

    // An index over the field's own "langcode" property must likewise
    // target its own column, and must not throw or collide with the
    // table's built-in langcode index.
    $field_storage->setIndexes([
      'value' => ['value'],
      'own_langcode' => ['langcode'],
    ]);
    $field_storage->save();
    $this->assertTrue(
      \Drupal::database()->schema()->indexExists($table, 'field_reserved_lang_own_langcode'),
      "The new index over the field's own langcode column was created on the dedicated field table."
    );
  }

  /**
   * Tests field type modules forbidding an update.
   */
  public function testUpdateForbid(): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'forbidden',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
      'settings' => [
        'changeable' => 0,
        'unchangeable' => 0,
      ],
    ]);
    $field_storage->save();
    $field_storage->setSetting('changeable', $field_storage->getSetting('changeable') + 1);
    try {
      $field_storage->save();
    }
    catch (FieldStorageDefinitionUpdateForbiddenException $e) {
      $this->fail('An unchangeable setting cannot be updated.');
    }
    $field_storage->setSetting('unchangeable', $field_storage->getSetting('unchangeable') + 1);
    try {
      $field_storage->save();
      $this->fail('An unchangeable setting can be updated.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(FieldStorageDefinitionUpdateForbiddenException::class, $e);
    }
  }

}
