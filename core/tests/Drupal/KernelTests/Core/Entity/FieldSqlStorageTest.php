<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Entity\Exception\FieldStorageDefinitionUpdateForbiddenException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore basefield
/**
 * Tests Field SQL Storage .
 *
 * Field_sql_storage.module implements the default back-end storage plugin
 * for the Field Storage API.
 */
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class FieldSqlStorageTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field', 'field_test', 'text', 'entity_test'];

  /**
   * The name of the created field.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The cardinality of the field.
   *
   * @var int
   */
  protected $fieldCardinality;

  /**
   * A field storage to use in this class.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * A field to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * Name of the data table of the field.
   *
   * @var string
   */
  protected $table;

  /**
   * Name of the revision table of the field.
   *
   * @var string
   */
  protected $revisionTable;

  /**
   * The table mapping for the tested entity type.
   *
   * @var \Drupal\Core\Entity\Sql\DefaultTableMapping
   */
  protected $tableMapping;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');
    $entity_type = 'entity_test_rev';

    $this->fieldName = $this->randomMachineName();
    $this->fieldCardinality = 4;
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $entity_type,
      'type' => 'test_field',
      'cardinality' => $this->fieldCardinality,
    ]);
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => $entity_type,
    ]);
    $this->field->save();

    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = \Drupal::entityTypeManager()->getStorage($entity_type)->getTableMapping();
    $this->tableMapping = $table_mapping;
    $this->table = $table_mapping->getDedicatedDataTableName($this->fieldStorage);
    $this->revisionTable = $table_mapping->getDedicatedRevisionTableName($this->fieldStorage);
  }

  /**
   * Tests field loading works correctly by inserting directly in the tables.
   */
  public function testFieldLoad(): void {
    $entity_type = $bundle = 'entity_test_rev';
    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage($entity_type);
    $connection = Database::getConnection();
    $json_entity_storage = $connection->supportsJsonEntityStorage();

    $value_column = $this->tableMapping->getFieldColumnName($this->fieldStorage, 'value');
    $columns = [
      'bundle',
      'deleted',
      'entity_id',
      'revision_id',
      'delta',
      'langcode',
      $value_column,
    ];

    // Create an entity with four revisions.
    $revision_ids = [];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create();
    $entity->save();
    $revision_ids[] = $entity->getRevisionId();
    for ($i = 0; $i < 4; $i++) {
      $entity->setNewRevision();
      $entity->save();
      $revision_ids[] = $entity->getRevisionId();
    }

    // Generate values (one value too many per revision, to prove cardinality is
    // enforced on load).
    $values = [];
    foreach ($revision_ids as $revision_id) {
      for ($delta = 0; $delta <= $this->fieldCardinality; $delta++) {
        $values[$revision_id][] = mt_rand(1, 127);
      }
    }
    // The "current" revision is the last one saved.
    $last_revision_id = (int) $revision_id;

    if ($json_entity_storage) {
      // JSON storage has no physical dedicated field tables; a revisionable
      // entity's field data is embedded in the base row's `__data_*` jsonb
      // columns. Inject the generated values directly into those documents to
      // mirror the relational direct inserts: every revision's data goes into
      // the all-revisions document (read by loadRevision()); the current
      // revision's data also goes into the current-revision document (read by
      // load()).
      $this->insertEmbeddedFieldValues($storage, $entity, $values, $last_revision_id, $value_column, $bundle);
    }
    else {
      // Insert the values directly in the dedicated storage tables.
      $query = $connection->insert($this->revisionTable)->fields($columns);
      foreach ($revision_ids as $revision_id) {
        foreach ($values[$revision_id] as $delta => $value) {
          $query->values([$bundle, 0, $entity->id(), $revision_id, $delta, $entity->language()->getId(), $value]);
        }
        $query->execute();
      }
      $query = $connection->insert($this->table)->fields($columns);
      foreach ($values[$last_revision_id] as $delta => $value) {
        $query->values([$bundle, 0, $entity->id(), $last_revision_id, $delta, $entity->language()->getId(), $value]);
      }
      $query->execute();
    }

    // Load every revision and check the values.
    foreach ($revision_ids as $revision_id) {
      $entity = $storage->loadRevision($revision_id);
      foreach ($values[$revision_id] as $delta => $value) {
        if ($delta < $this->fieldCardinality) {
          $this->assertEquals($value, $entity->{$this->fieldName}[$delta]->value);
        }
        else {
          $this->assertArrayNotHasKey($delta, $entity->{$this->fieldName});
        }
      }
    }

    // Load the "current revision" and check the values.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $storage->load($entity->id());
    foreach ($values[$last_revision_id] as $delta => $value) {
      if ($delta < $this->fieldCardinality) {
        $this->assertEquals($value, $entity->{$this->fieldName}[$delta]->value);
      }
      else {
        $this->assertArrayNotHasKey($delta, $entity->{$this->fieldName});
      }
    }

    // Add a translation in an unavailable language code and verify it is not
    // loaded.
    $unavailable_langcode = 'xx';
    if ($json_entity_storage) {
      $this->insertEmbeddedUnavailableLangcode($storage, $entity, $unavailable_langcode, $value_column, $bundle);
    }
    else {
      $values = [$bundle, 0, $entity->id(), $entity->getRevisionId(), 0, $unavailable_langcode, mt_rand(1, 127)];
      $connection->insert($this->table)->fields($columns)->values($values)->execute();
      $connection->insert($this->revisionTable)->fields($columns)->values($values)->execute();
    }
    $entity = $storage->load($entity->id());
    $this->assertArrayNotHasKey($unavailable_langcode, $entity->{$this->fieldName});
  }

  /**
   * Resolves the `__data_*` jsonb column that backs an embedded revision table.
   *
   * @param \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage
   *   The entity storage.
   * @param string $container_table
   *   The embedded revision table name (e.g. "<entity>_all_revisions").
   *
   * @return string
   *   The physical jsonb column name (e.g. "__data_all_revisions").
   */
  protected function embeddedDataColumn($storage, string $container_table): string {
    $base_table = $storage->getEntityType()->getBaseTable();
    return '__data' . substr($container_table, strlen($base_table));
  }

  /**
   * Injects generated field values into the embedded JSONB revision documents.
   *
   * Mirrors the relational direct inserts of ::testFieldLoad() for the pgsql
   * JSON storage driver: the field rows for each revision are nested into the
   * all-revisions document under the dedicated embedded-table key, and the last
   * (current) revision's rows are additionally nested into the current-revision
   * document.
   *
   * @param \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose base row holds the embedded documents.
   * @param array $values
   *   Field values keyed by revision ID, then delta.
   * @param int $last_revision_id
   *   The current (default) revision ID.
   * @param string $value_column
   *   The prefixed value column name (e.g. "<field>_value").
   * @param string $bundle
   *   The entity bundle.
   */
  protected function insertEmbeddedFieldValues($storage, $entity, array $values, int $last_revision_id, string $value_column, string $bundle): void {
    $table_mapping = $storage->getTableMapping();
    $langcode = $entity->language()->getId();

    // Build the nested field rows for a single revision.
    $make_rows = function (int $revision_id) use ($values, $bundle, $entity, $langcode, $value_column): array {
      $rows = [];
      foreach ($values[$revision_id] as $delta => $value) {
        $rows[] = [
          'delta' => $delta,
          'bundle' => $bundle,
          'deleted' => FALSE,
          'langcode' => $langcode,
          'entity_id' => (string) $entity->id(),
          'revision_id' => (string) $revision_id,
          $value_column => $value,
        ];
      }
      return $rows;
    };

    // Nest every revision's rows into the all-revisions document.
    $all_container = $table_mapping->getJsonStorageAllRevisionsTable();
    $all_dedicated = $table_mapping->getJsonStorageDedicatedTableName($this->fieldStorage, $all_container);
    $this->updateEmbeddedDocument($storage, $entity, $all_container, function (array $document) use ($all_container, $all_dedicated, $make_rows): array {
      foreach ($document[$all_container] as &$revision_document) {
        $revision_document[$all_dedicated] = $make_rows((int) $revision_document['revision_id']);
      }
      return $document;
    });

    // Nest the current revision's rows into the current-revision document.
    $current_container = $table_mapping->getJsonStorageCurrentRevisionTable();
    $current_dedicated = $table_mapping->getJsonStorageDedicatedTableName($this->fieldStorage, $current_container);
    $this->updateEmbeddedDocument($storage, $entity, $current_container, function (array $document) use ($current_container, $current_dedicated, $make_rows, $last_revision_id): array {
      foreach ($document[$current_container] as &$revision_document) {
        if ((int) $revision_document['revision_id'] === $last_revision_id) {
          $revision_document[$current_dedicated] = $make_rows($last_revision_id);
        }
      }
      return $document;
    });
  }

  /**
   * Appends an unavailable-language field row to the current-revision document.
   *
   * @param \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose base row holds the embedded documents.
   * @param string $langcode
   *   The unavailable language code.
   * @param string $value_column
   *   The prefixed value column name (e.g. "<field>_value").
   * @param string $bundle
   *   The entity bundle.
   */
  protected function insertEmbeddedUnavailableLangcode($storage, $entity, string $langcode, string $value_column, string $bundle): void {
    $table_mapping = $storage->getTableMapping();
    $current_container = $table_mapping->getJsonStorageCurrentRevisionTable();
    $current_dedicated = $table_mapping->getJsonStorageDedicatedTableName($this->fieldStorage, $current_container);
    $row = [
      'delta' => 0,
      'bundle' => $bundle,
      'deleted' => FALSE,
      'langcode' => $langcode,
      'entity_id' => (string) $entity->id(),
      'revision_id' => (string) $entity->getRevisionId(),
      $value_column => mt_rand(1, 127),
    ];
    $this->updateEmbeddedDocument($storage, $entity, $current_container, function (array $document) use ($current_container, $current_dedicated, $row): array {
      foreach ($document[$current_container] as &$revision_document) {
        $revision_document[$current_dedicated][] = $row;
      }
      return $document;
    });
  }

  /**
   * Read-modify-writes an embedded JSONB revision document column.
   *
   * @param \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose base row holds the embedded documents.
   * @param string $container_table
   *   The embedded revision table name backing the column to rewrite.
   * @param callable $callback
   *   Receives the decoded document array and returns the modified array.
   */
  protected function updateEmbeddedDocument($storage, $entity, string $container_table, callable $callback): void {
    $connection = Database::getConnection();
    $base_table = $storage->getEntityType()->getBaseTable();
    $id_key = $storage->getEntityType()->getKey('id');
    $column = $this->embeddedDataColumn($storage, $container_table);

    $raw = $connection->select($base_table, 'b')
      ->fields('b', [$column])
      ->condition($id_key, $entity->id())
      ->execute()
      ->fetchField();
    $document = $raw ? json_decode($raw, TRUE) : [];
    $document = $callback($document);

    $connection->update($base_table)
      ->fields([$column => json_encode($document)])
      ->condition($id_key, $entity->id())
      ->execute();
  }

  /**
   * Reads the stored field rows for one context, keyed by delta.
   *
   * Abstracts over the two storage layouts so ::testFieldWrite() can assert on
   * the same row structure for both. On relational drivers this reads the
   * physical dedicated data/revision table directly. On the pgsql JSON storage
   * driver there are no such tables: the field rows live nested inside the base
   * row's `__data*` jsonb column, under the container document keyed by the
   * embedded table name (@see
   * \Drupal\Core\Entity\Sql\SqlContentEntityStorage::readJsonStorageDedicatedRows()).
   *
   * @param \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose stored field rows should be read.
   * @param bool $revision
   *   TRUE to read the all-revisions context (the physical revision table under
   *   relational storage), FALSE to read the current-revision context (the
   *   physical data table).
   * @param int|null $revision_id
   *   (optional) When reading the all-revisions context, restrict the rows to
   *   this revision ID.
   *
   * @return array
   *   The stored field rows keyed by delta.
   */
  protected function readStoredFieldRows($storage, $entity, bool $revision, ?int $revision_id = NULL): array {
    $connection = Database::getConnection();
    if (!$connection->supportsJsonEntityStorage()) {
      $query = $connection->select($revision ? $this->revisionTable : $this->table, 't')->fields('t');
      if ($revision_id !== NULL) {
        $query->condition('revision_id', $revision_id);
      }
      return $query->execute()->fetchAllAssoc('delta', FetchAs::Associative);
    }

    // JSON storage: pull the container document out of the base row's `__data*`
    // jsonb column and collect the field's dedicated rows nested within it.
    $container = $revision
      ? $this->tableMapping->getJsonStorageAllRevisionsTable()
      : $this->tableMapping->getJsonStorageCurrentRevisionTable();
    $dedicated = $this->tableMapping->getJsonStorageDedicatedTableName($this->fieldStorage, $container);
    $column = $this->embeddedDataColumn($storage, $container);

    $base_table = $storage->getEntityType()->getBaseTable();
    $id_key = $storage->getEntityType()->getKey('id');
    $raw = $connection->select($base_table, 'b')
      ->fields('b', [$column])
      ->condition($id_key, $entity->id())
      ->execute()
      ->fetchField();
    $document = $raw ? json_decode($raw, TRUE) : [];

    $rows = [];
    foreach ($document[$container] ?? [] as $revision_document) {
      if ($revision_id !== NULL && (int) $revision_document['revision_id'] !== $revision_id) {
        continue;
      }
      foreach ($revision_document[$dedicated] ?? [] as $row) {
        $rows[(int) $row['delta']] = $row;
      }
    }
    ksort($rows);
    return $rows;
  }

  /**
   * Tests field saving works correctly by reading directly from the tables.
   */
  public function testFieldWrite(): void {
    $entity_type = $bundle = 'entity_test_rev';
    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage($entity_type);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $storage->create();

    $revision_values = [];

    // Check insert. Add one value too many.
    $values = [];
    for ($delta = 0; $delta <= $this->fieldCardinality; $delta++) {
      $values[$delta]['value'] = mt_rand(1, 127);
    }
    $entity->{$this->fieldName} = $values;
    $entity->save();

    // Read the storage and check the correct values have been stored.
    $rows = $this->readStoredFieldRows($storage, $entity, FALSE);
    $this->assertCount($this->fieldCardinality, $rows);
    foreach ($rows as $delta => $row) {
      $expected = [
        'bundle' => $bundle,
        'deleted' => 0,
        'entity_id' => $entity->id(),
        'revision_id' => $entity->getRevisionId(),
        'langcode' => $entity->language()->getId(),
        'delta' => $delta,
        $this->fieldName . '_value' => $values[$delta]['value'],
      ];
      $this->assertEquals($expected, $row, "Row {$delta} was stored as expected.");
    }

    // Test update. Add less values and check that the previous values did not
    // persist.
    $values = [];
    for ($delta = 0; $delta <= $this->fieldCardinality - 2; $delta++) {
      $values[$delta]['value'] = mt_rand(1, 127);
    }
    $values_count = count($values);
    $entity->{$this->fieldName} = $values;
    $entity->save();
    $rows = $this->readStoredFieldRows($storage, $entity, FALSE);
    $this->assertCount($values_count, $rows);
    foreach ($rows as $delta => $row) {
      $expected = [
        'bundle' => $bundle,
        'deleted' => 0,
        'entity_id' => $entity->id(),
        'revision_id' => $entity->getRevisionId(),
        'langcode' => $entity->language()->getId(),
        'delta' => $delta,
        $this->fieldName . '_value' => $values[$delta]['value'],
      ];
      $this->assertEquals($expected, $row, "Row {$delta} was stored as expected.");
    }

    // Create a new revision.
    $revision_values[$entity->getRevisionId()] = $values;
    $values = [];
    for ($delta = 0; $delta < $this->fieldCardinality; $delta++) {
      $values[$delta]['value'] = mt_rand(1, 127);
    }
    $entity->{$this->fieldName} = $values;
    $entity->setNewRevision();
    $entity->save();
    $revision_values[$entity->getRevisionId()] = $values;

    // Check that data for both revisions are in the revision storage.
    foreach ($revision_values as $revision_id => $values) {
      $rows = $this->readStoredFieldRows($storage, $entity, TRUE, $revision_id);
      $this->assertCount(min(count($values), $this->fieldCardinality), $rows);
      foreach ($rows as $delta => $row) {
        $expected = [
          'bundle' => $bundle,
          'deleted' => 0,
          'entity_id' => $entity->id(),
          'revision_id' => $revision_id,
          'langcode' => $entity->language()->getId(),
          'delta' => $delta,
          $this->fieldName . '_value' => $values[$delta]['value'],
        ];
        $this->assertEquals($expected, $row, "Row {$delta} was stored as expected.");
      }
    }

    // Test emptying the field.
    $entity->{$this->fieldName} = NULL;
    $entity->save();
    $rows = $this->readStoredFieldRows($storage, $entity, FALSE);
    $this->assertCount(0, $rows);
  }

  /**
   * Tests that long entity type and field names do not break.
   */
  public function testLongNames(): void {
    // Use one of the longest entity_type names in core.
    $entity_type = $bundle = 'entity_test_multivalue_basefield';
    $this->installEntitySchema('entity_test_multivalue_basefield');
    $storage = $this->container->get('entity_type.manager')->getStorage($entity_type);

    // Create two fields and generate random values.
    $name_base = $this->randomMachineName(FieldStorageConfig::NAME_MAX_LENGTH - 1);
    $field_names = [];
    $values = [];
    for ($i = 0; $i < 2; $i++) {
      $field_names[$i] = $name_base . $i;
      FieldStorageConfig::create([
        'field_name' => $field_names[$i],
        'entity_type' => $entity_type,
        'type' => 'test_field',
      ])->save();
      FieldConfig::create([
        'field_name' => $field_names[$i],
        'entity_type' => $entity_type,
        'bundle' => $bundle,
      ])->save();
      $values[$field_names[$i]] = mt_rand(1, 127);
    }

    // Save an entity with values.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create($values);
    $entity->save();

    // Load the entity back and check the values.
    $entity = $storage->load($entity->id());
    foreach ($field_names as $field_name) {
      $this->assertEquals($values[$field_name], $entity->get($field_name)->value);
    }
  }

  /**
   * Tests trying to update a field with data.
   */
  public function testUpdateFieldSchemaWithData(): void {
    $entity_type = 'entity_test_rev';
    // Create a decimal 5.2 field and add some data.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'decimal52',
      'entity_type' => $entity_type,
      'type' => 'decimal',
      'settings' => ['precision' => 5, 'scale' => 2],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $entity_type,
    ]);
    $field->save();
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'id' => 0,
        'revision_id' => 0,
      ]);
    $entity->decimal52->value = '1.235';
    $entity->save();

    // Attempt to update the field in a way that would work without data.
    $field_storage->setSetting('scale', 3);
    $this->expectException(FieldStorageDefinitionUpdateForbiddenException::class);
    $field_storage->save();
  }

  /**
   * Tests that failure to create fields is handled gracefully.
   */
  public function testFieldUpdateFailure(): void {
    if (Database::getConnection()->supportsJsonEntityStorage()) {
      $this->markTestSkipped('With JSON entity storage, the physical dedicated field tables do not exist.');
    }
    // Create a text field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_text',
      'entity_type' => 'entity_test_rev',
      'type' => 'text',
      'settings' => ['max_length' => 255],
    ]);
    $field_storage->save();

    // Attempt to update the field in a way that would break the storage. The
    // parenthesis suffix is needed because SQLite has *very* relaxed rules for
    // data types, so we actually need to provide an invalid SQL syntax in order
    // to break it.
    // @see https://www.sqlite.org/datatype3.html
    $prior_field_storage = $field_storage;
    $field_storage->setSetting('max_length', '-1)');
    try {
      $field_storage->save();
      $this->fail('Update succeeded.');
    }
    catch (\Exception) {
      // Expected exception; just continue testing.
    }

    // Ensure that the field tables are still there.
    $tables = [
      $this->tableMapping->getDedicatedDataTableName($prior_field_storage),
      $this->tableMapping->getDedicatedRevisionTableName($prior_field_storage),
    ];
    $schema = Database::getConnection()->schema();
    foreach ($tables as $table_name) {
      $this->assertTrue($schema->tableExists($table_name), 'Table $table_name exists.');
    }
  }

  /**
   * Tests adding and removing indexes while data is present.
   */
  public function testFieldUpdateIndexesWithData(): void {
    if (Database::getConnection()->supportsJsonEntityStorage()) {
      $this->markTestSkipped('With JSON entity storage, the physical dedicated field tables do not exist.');
    }
    // Create a decimal field.
    $field_name = 'test_field';
    $entity_type = 'entity_test_rev';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'text',
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $entity_type,
    ]);
    $field->save();
    $tables = [
      $this->tableMapping->getDedicatedDataTableName($field_storage),
      $this->tableMapping->getDedicatedRevisionTableName($field_storage),
    ];

    // Verify the indexes we will create do not exist yet.
    foreach ($tables as $table) {
      $this->assertFalse(Database::getConnection()->schema()->indexExists($table, 'value'), 'No index named value exists in $table');
      $this->assertFalse(Database::getConnection()->schema()->indexExists($table, 'value_format'), 'No index named value_format exists in $table');
    }

    // Add data so the table cannot be dropped.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'id' => 1,
        'revision_id' => 1,
      ]);
    $entity->$field_name->value = 'field data';
    $entity->enforceIsNew();
    $entity->save();

    // Add an index.
    $field_storage->setIndexes(['value' => [['value', 255]]]);
    $field_storage->save();
    foreach ($tables as $table) {
      $this->assertTrue(Database::getConnection()->schema()->indexExists($table, "{$field_name}_value"), "Index on value created in $table");
    }

    // Add a different index, removing the existing custom one.
    $field_storage->setIndexes(['value_format' => [['value', 127], ['format', 127]]]);
    $field_storage->save();
    foreach ($tables as $table) {
      $this->assertTrue(Database::getConnection()->schema()->indexExists($table, "{$field_name}_value_format"), "Index on value_format created in $table");
      $this->assertFalse(Database::getConnection()->schema()->indexExists($table, "{$field_name}_value"), "Index on value removed in $table");
    }

    // Verify that the tables were not dropped in the process.
    $entity = $this->container->get('entity_type.manager')->getStorage($entity_type)->load(1);
    $this->assertEquals('field data', $entity->{$field_name}->value);
  }

  /**
   * Tests foreign key support.
   */
  public function testFieldSqlStorageForeignKeys(): void {
    // Create a 'shape' field, with a configurable foreign key (see
    // field_test_field_schema()).
    $field_name = 'test_field';
    $foreign_key_name = 'shape';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'shape',
      'settings' => ['foreign_key_name' => $foreign_key_name],
    ]);
    $field_storage->save();
    // Get the field schema.
    $schema = $field_storage->getSchema();

    // Retrieve the field definition and check that the foreign key is in place.
    $this->assertEquals($foreign_key_name, $schema['foreign keys'][$foreign_key_name]['table'], 'Foreign key table name preserved through CRUD');
    $this->assertEquals('id', $schema['foreign keys'][$foreign_key_name]['columns'][$foreign_key_name], 'Foreign key column name preserved through CRUD');

    // Update the field settings, it should update the foreign key definition
    // too.
    $foreign_key_name = 'color';
    $field_storage->setSetting('foreign_key_name', $foreign_key_name);
    $field_storage->save();
    // Reload the field schema after the update.
    $schema = $field_storage->getSchema();

    // Check that the foreign key is in place.
    $this->assertEquals($foreign_key_name, $schema['foreign keys'][$foreign_key_name]['table'], 'Foreign key table name modified after update');
    $this->assertEquals('id', $schema['foreign keys'][$foreign_key_name]['columns'][$foreign_key_name], 'Foreign key column name modified after update');
  }

  /**
   * Tests table name generation.
   */
  public function testTableNames(): void {
    // Note: we need to test entity types with long names. We therefore use
    // fields on imaginary entity types (works as long as we don't actually save
    // them), and just check the generated table names.

    // Short entity type and field name.
    $entity_type = 'short_entity_type';
    $field_name = 'short_field_name';
    $field_storage = FieldStorageConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'type' => 'test_field',
    ]);
    $expected = 'short_entity_type__short_field_name';
    $this->assertEquals($expected, $this->tableMapping->getDedicatedDataTableName($field_storage));
    $expected = 'short_entity_type_revision__short_field_name';
    $this->assertEquals($expected, $this->tableMapping->getDedicatedRevisionTableName($field_storage));

    // Short entity type, long field name.
    $entity_type = 'short_entity_type';
    $field_name = 'long_field_name_abcdefghijklmnopqrstuvwxyz';
    $field_storage = FieldStorageConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'type' => 'test_field',
    ]);
    $expected = 'short_entity_type__' . substr(hash('sha256', $field_storage->uuid()), 0, 10);
    $this->assertEquals($expected, $this->tableMapping->getDedicatedDataTableName($field_storage));
    $expected = 'short_entity_type_r__' . substr(hash('sha256', $field_storage->uuid()), 0, 10);
    $this->assertEquals($expected, $this->tableMapping->getDedicatedRevisionTableName($field_storage));

    // Long entity type, short field name.
    $entity_type = 'long_entity_type_all_forty_three_characters';
    $field_name = 'short_field_name';
    $field_storage = FieldStorageConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'type' => 'test_field',
    ]);
    $expected = 'long_entity_type_all_forty_three__' . substr(hash('sha256', $field_storage->uuid()), 0, 10);
    $this->assertEquals($expected, $this->tableMapping->getDedicatedDataTableName($field_storage));
    $expected = 'long_entity_type_all_forty_three_r__' . substr(hash('sha256', $field_storage->uuid()), 0, 10);
    $this->assertEquals($expected, $this->tableMapping->getDedicatedRevisionTableName($field_storage));

    // Long entity type and field name.
    $entity_type = 'long_entity_type_all_forty_three_characters';
    $field_name = 'long_field_name_using_forty_two_characters';
    $field_storage = FieldStorageConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'type' => 'test_field',
    ]);
    $expected = 'long_entity_type_all_forty_three__' . substr(hash('sha256', $field_storage->uuid()), 0, 10);
    $this->assertEquals($expected, $this->tableMapping->getDedicatedDataTableName($field_storage));
    $expected = 'long_entity_type_all_forty_three_r__' . substr(hash('sha256', $field_storage->uuid()), 0, 10);
    $this->assertEquals($expected, $this->tableMapping->getDedicatedRevisionTableName($field_storage));
    // Try creating a second field and check there are no clashes.
    $field_storage2 = FieldStorageConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $field_name . '2',
      'type' => 'test_field',
    ]);
    $this->assertNotEquals($this->tableMapping->getDedicatedDataTableName($field_storage), $this->tableMapping->getDedicatedDataTableName($field_storage2));
    $this->assertNotEquals($this->tableMapping->getDedicatedRevisionTableName($field_storage), $this->tableMapping->getDedicatedRevisionTableName($field_storage2));

    // Deleted field.
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'some_entity_type',
      'field_name' => 'some_field_name',
      'type' => 'test_field',
      'deleted' => TRUE,
    ]);
    $expected = 'field_deleted_data_' . substr(hash('sha256', $field_storage->uuid()), 0, 10);
    $this->assertEquals($expected, $this->tableMapping->getDedicatedDataTableName($field_storage, TRUE));
    $expected = 'field_deleted_revision_' . substr(hash('sha256', $field_storage->uuid()), 0, 10);
    $this->assertEquals($expected, $this->tableMapping->getDedicatedRevisionTableName($field_storage, TRUE));

    // Check that the table mapping is kept up-to-date in a request where a new
    // field storage definition is added. Since the cardinality of the field is
    // greater than 1, the table name retrieved from getFieldTableName() should
    // be the dedicated table.
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test_rev',
      'field_name' => 'some_field_name',
      'type' => 'test_field',
      'cardinality' => 2,
    ]);
    $field_storage->save();
    $table_mapping = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->getTableMapping();
    if (Database::getConnection()->supportsJsonEntityStorage()) {
      // A multi-valued field on a revisionable entity is embedded in the
      // current-revision document, not a physical dedicated data table.
      $this->assertEquals($table_mapping->getJsonStorageDedicatedTableName($field_storage, $table_mapping->getJsonStorageCurrentRevisionTable()), $table_mapping->getFieldTableName('some_field_name'));
    }
    else {
      $this->assertEquals($table_mapping->getDedicatedDataTableName($field_storage), $table_mapping->getFieldTableName('some_field_name'));
    }
  }

  /**
   * Tests loading base and configurable serialized fields with NULL values.
   *
   * Note that this tests loading field values that are literally NULL. It
   * doesn't test that NULL values are serialized and unserialized properly.
   */
  public function testNullSerializedFieldLoad(): void {
    $connection = Database::getConnection();
    $entity_type = $bundle = 'entity_test_serialized_field';
    $this->installEntitySchema($entity_type);
    $storage = $this->container->get('entity_type.manager')->getStorage($entity_type);

    // The serialized base field is already part of the test entity type, but
    // a configurable field has to be created.
    $serialized_field = $this->randomMachineName();
    $field_storage = FieldStorageConfig::create([
      'field_name' => $serialized_field,
      'entity_type' => $entity_type,
      'type' => 'nullable_serialized_item_test',
      'cardinality' => 1,
      'required' => FALSE,
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
    ]);
    $field->save();

    $entity = $storage->create();
    $entity->save();

    // Manually set the field values to NULL with database queries. The
    // single-value serialized base field is a real base-table column on every
    // driver, so it is nulled with a plain UPDATE.
    $connection->update('entity_test_serialized_fields')
      ->fields(['serialized' => NULL])
      ->condition('id', $entity->id())
      ->execute();
    if ($connection->supportsJsonEntityStorage()) {
      $table_mapping = $storage->getTableMapping();
      $base_table = $storage->getEntityType()->getBaseTable();
      $embedded_table = $table_mapping->getJsonStorageDedicatedTableName($field_storage, $base_table);
      $document = [
        $embedded_table => [
          [
            'bundle' => $bundle,
            'deleted' => 0,
            'entity_id' => (int) $entity->id(),
            'revision_id' => (int) $entity->id(),
            'langcode' => $entity->language()->getId(),
            'delta' => 0,
            // The value key ($serialized_field . '_value') is intentionally
            // omitted to represent a NULL field value.
          ],
        ],
      ];
      $connection->update('entity_test_serialized_fields')
        ->fields(['__data' => json_encode($document)])
        ->condition('id', $entity->id())
        ->execute();
    }
    else {
      $connection->insert($entity_type . '__' . $serialized_field)
        ->fields([
          'bundle' => $bundle,
          'deleted' => 0,
          'entity_id' => $entity->id(),
          'revision_id' => $entity->id(),
          'langcode' => $entity->language()->getId(),
          'delta' => 0,
          $serialized_field . '_value' => NULL,
        ])
        ->execute();
    }

    $storage->load($entity->id());
    $this->assertNull($entity->serialized->value);
    $this->assertNull($entity->{$serialized_field}->value);
  }

}
