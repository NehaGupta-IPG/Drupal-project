<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;

/**
 * Tests update of layout_builder__layout_section fields to longblob.
 *
 * @group layout_builder
 */
class LayoutBuilderLayoutSectionDBLimitUpdateTest extends UpdatePathTestBase {

  /**
   * The database.
   */
  protected Connection $connection;

  /**
   * The name of the test database.
   */
  protected string $databaseName;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->connection = \Drupal::service('database');
    if ($this->connection->databaseType() === 'pgsql') {
      $this->databaseName = 'public';
    }
    else {
      $this->databaseName = $this->connection->getConnectionOptions()['database'];
    }

    $this->createContentType(['type' => 'bundle_with_section_field']);
    LayoutBuilderEntityViewDisplay::load('node.bundle_with_section_field.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    // Revert field type to previous blob value.
    \Drupal::database()->schema()->changeField('node__layout_builder__layout', 'layout_builder__layout_section', 'layout_builder__layout_section', [
      'type' => 'blob',
      'default' => NULL,
    ]);
    \Drupal::database()->schema()->changeField('node_revision__layout_builder__layout', 'layout_builder__layout_section', 'layout_builder__layout_section', [
      'type' => 'blob',
      'default' => NULL,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles[] = DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz';
    $this->databaseDumpFiles[] = DRUPAL_ROOT . '/core/modules/layout_builder/tests/fixtures/update/layout-builder.php';
  }

  /**
   * Assert equals expected data type of layout section column.
   */
  protected function assertEqualLayoutSectionDataType($expected): void {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $field_map = $entity_field_manager->getFieldMapByFieldType('layout_section');
    if (empty($field_map)) {
      $this->fail('Layout builder not enabled.');
    }

    foreach ($field_map as $entity_type_id => $layout_section_fields) {
      $entity_storage = $entity_type_manager->getStorage($entity_type_id);

      // Skip this entity type if it does not use SQL-based storage.
      if (!$entity_storage instanceof SqlEntityStorageInterface) {
        $this->fail();
      }

      $entity_type = $entity_type_manager->getDefinition($entity_type_id);

      // Load all field storage definitions for the entity type.
      $field_storage_definitions = $entity_field_manager->getFieldStorageDefinitions($entity_type_id);
      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
      $table_mapping = $entity_storage->getTableMapping($field_storage_definitions);

      // Get the field storage definitions for all the layout_section fields.
      $field_storage_definitions = array_intersect_key($field_storage_definitions, $layout_section_fields);

      // Iterate over each layout_section field definition.
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_definition */
      foreach ($field_storage_definitions as $field_storage_definition) {
        $field_name = $field_storage_definition->getName();

        // Determine which tables we need to update.
        $tables = [
          $table_mapping->getFieldTableName($field_name),
        ];
        if ($entity_type->isRevisionable() && $field_storage_definition->isRevisionable()) {
          $tables[] = $table_mapping->getDedicatedRevisionTableName($field_storage_definition);
        }

        // Alter the database column for each of the tables.
        $column_name = $table_mapping->getColumnNames($field_name)['section'];

        // Check data type for each table.
        foreach ($tables as $table) {
          $db_name = $this->databaseName;
          $table_with_prefix = $this->connection->getPrefix() . $table;
          $query = "SELECT data_type FROM information_schema.columns WHERE table_schema = '$db_name' and table_name = '$table_with_prefix' and column_name = '$column_name';";
          $result = $this->connection->query($query)->fetchField();

          $this->assertEquals($expected, $result);
        }
      }
    }
  }

  /**
   * Tests layout_builder_post_update_section_field_size_increase post update.
   */
  public function testUpdatePostUpdate(): void {
    $this->assertEqualLayoutSectionDataType('blob');
    $this->runUpdates();
    $this->assertEqualLayoutSectionDataType('longblob');
  }

}
