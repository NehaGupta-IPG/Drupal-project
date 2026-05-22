<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel;

use Drupal\KernelTests\KernelTestBase;
use MongoDB\Model\IndexInfo;

// phpcs:ignoreFile

/**
 * Base class for MongoDB schema tests.
 *
 * @group MongoDB
 * @coversDefaultClass \Drupal\mongodb\Driver\Database\mongodb\Schema
 */
class SchemaTestBase extends KernelTestBase {

  /**
   * An array with the test_table1: name, specification and validation data.
   *
   * @var array
   */
  CONST TEST_TABLE1 = [
    'name' => 'test_table1',
    'schema' => [
      'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
      'fields' => [
        'id'  => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => NULL,
        ],
        'test_field' => [
          'type' => 'int',
          'not null' => TRUE,
          'description' => 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
        ],
        'test_field_string'  => [
          'type' => 'varchar',
          'length' => 20,
          'not null' => TRUE,
          'default' => "'\"Quoting fun!'\"",
          'description' => 'Schema column description for string.',
        ],
        'test_field_string_ascii'  => [
          'type' => 'varchar_ascii',
          'length' => 255,
          'description' => 'Schema column description for ASCII string.',
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => ['test_field' => ['test_field']],
    ],
    'validation' => [
      '$and' => [
        ['id' => ['$type' => 'int']],
        ['id' => ['$exists' => TRUE]],
        ['test_field' => ['$type' => 'int']],
        ['test_field' => ['$exists' => TRUE]],
        ['test_field_string' => ['$type' => 'string']],
        ['test_field_string' => ['$exists' => TRUE]],
        [
          '$or' => [
            ['test_field_string_ascii' => ['$type' => 'string']],
            ['test_field_string_ascii' => ['$exists' => FALSE]],
          ],
        ],
      ],
    ],
    'indexes' => [
      ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
      ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
      ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
    ],
  ];

  /**
   * An array with the test_table2: name, specification and validation data.
   *
   * @var array
   */
  CONST TEST_TABLE2 = [
    'name' => 'test_table2',
    'schema' => [
      'description' => 'Schema table for testing integer with null, not null and default values.',
      'fields' => [
        'id'  => [
          'type' => 'serial',
        ],
        'test_field_int_null'  => [
          'type' => 'int',
          'not null' => FALSE,
        ],
        'test_field_int_not_null'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'test_field_int_default'  => [
          'type' => 'int',
          'default' => 4,
        ],
        'test_field_float_null'  => [
          'type' => 'float',
          'not null' => FALSE,
        ],
        'test_field_float_not_null'  => [
          'type' => 'float',
          'not null' => TRUE,
        ],
        'test_field_float_default'  => [
          'type' => 'float',
          'default' => 4,
        ],
        'test_field_numeric_null'  => [
          'type' => 'numeric',
          'not null' => FALSE,
        ],
        'test_field_numeric_not_null'  => [
          'type' => 'numeric',
          'not null' => TRUE,
        ],
        'test_field_numeric_default'  => [
          'type' => 'numeric',
          'default' => 4,
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => [
        'test_fields_not_null' => ['test_field_int_not_null', 'test_field_float_not_null', 'test_field_numeric_not_null'],
        'test_fields_null' => ['test_field_int_null', 'test_field_float_null', 'test_field_numeric_null'],
      ],
      'indexes' => [
        'test_fields_default' => ['test_field_int_default', 'test_field_float_default', 'test_field_numeric_default'],
        'test_field_int_null' => ['test_field_int_null'],
      ],
    ],
    'validation' => [
      '$and' => [
        ['id' => ['$type' => 'int']],
        ['id' => ['$exists' => TRUE]],
        ['id' => ['$gte' => 0]],
        [
          '$or' => [
            ['test_field_int_null' => ['$type' => 'int']],
            ['test_field_int_null' => ['$exists' => FALSE]],
          ],
        ],
        ['test_field_int_not_null' => ['$type' => 'int']],
        ['test_field_int_not_null' => ['$exists' => TRUE]],
        [
          '$or' => [
            ['test_field_int_default' => ['$type' => 'int']],
            ['test_field_int_default' => ['$exists' => FALSE]],
          ],
        ],
        [
          '$or' => [
            ['test_field_float_null' => ['$type' => 'double']],
            ['test_field_float_null' => ['$exists' => FALSE]],
          ],
        ],
        ['test_field_float_not_null' => ['$type' => 'double']],
        ['test_field_float_not_null' => ['$exists' => TRUE]],
        [
          '$or' => [
            ['test_field_float_default' => ['$type' => 'double']],
            ['test_field_float_default' => ['$exists' => FALSE]],
          ],
        ],
        [
          '$or' => [
            ['test_field_numeric_null' => ['$type' => 'decimal']],
            ['test_field_numeric_null' => ['$exists' => FALSE]],
          ],
        ],
        ['test_field_numeric_not_null' => ['$type' => 'decimal']],
        ['test_field_numeric_not_null' => ['$exists' => TRUE]],
        [
          '$or' => [
            ['test_field_numeric_default' => ['$type' => 'decimal']],
            ['test_field_numeric_default' => ['$exists' => FALSE]],
          ],
        ],
      ],
    ],
    'indexes' => [
      ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
      ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
      ['name' => 'test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_field_int_not_null' => 1, 'test_field_float_not_null' => 1, 'test_field_numeric_not_null' => 1]],
      ['name' => 'test_fields_null__key', 'unique' => TRUE, 'key' => ['test_field_int_null' => 1, 'test_field_float_null' => 1, 'test_field_numeric_null' => 1]],
      ['name' => 'test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_field_int_default' => 1, 'test_field_float_default' => 1, 'test_field_numeric_default' => 1]],
      ['name' => 'test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_field_int_null' => 1]],
    ],
  ];

  /**
   * An array with the test_table3: name, specification and validation data.
   *
   * @var array
   */
  CONST TEST_TABLE3 = [
    'name' => 'test_table3',
    'schema' => [
      'description' => 'Schema table for testing varchar with null, not null and default values.',
      'fields' => [
        'id'  => [
          'type' => 'serial',
        ],
        'test_field_varchar_null'  => [
          'type' => 'varchar',
        ],
        'test_field_varchar_not_null'  => [
          'type' => 'varchar',
          'not null' => TRUE,
        ],
        'test_field_varchar_default'  => [
          'type' => 'varchar',
          'default' => 4,
        ],
        'test_field_text_null'  => [
          'type' => 'text',
        ],
        'test_field_text_not_null'  => [
          'type' => 'text',
          'not null' => TRUE,
        ],
        'test_field_text_default'  => [
          'type' => 'text',
          'default' => 4,
        ],
      ],
    ],
    'validation' => [
      '$and' => [
        ['id' => ['$type' => 'int']],
        ['id' => ['$exists' => TRUE]],
        ['id' => ['$gte' => 0]],
        [
          '$or' => [
            ['test_field_varchar_null' => ['$type' => 'string']],
            ['test_field_varchar_null' => ['$exists' => FALSE]],
          ],
        ],
        ['test_field_varchar_not_null' => ['$type' => 'string']],
        ['test_field_varchar_not_null' => ['$exists' => TRUE]],
        [
          '$or' => [
            ['test_field_varchar_default' => ['$type' => 'string']],
            ['test_field_varchar_default' => ['$exists' => FALSE]],
          ],
        ],
        [
          '$or' => [
            ['test_field_text_null' => ['$type' => 'string']],
            ['test_field_text_null' => ['$exists' => FALSE]],
          ],
        ],
        ['test_field_text_not_null' => ['$type' => 'string']],
        ['test_field_text_not_null' => ['$exists' => TRUE]],
        [
          '$or' => [
            ['test_field_text_default' => ['$type' => 'string']],
            ['test_field_text_default' => ['$exists' => FALSE]],
          ],
        ],
      ],
    ],
    'indexes' => [
      ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
    ],
  ];

  /**
   * An array with the test_table4: name, specification and validation data.
   *
   * @var array
   */
  CONST TEST_TABLE4 = [
    'name' => 'test_table4',
    'schema' => [
      'description' => 'Schema table with primary and unique key.',
      'fields' => [
        'id'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'test_field' => [
          'type' => 'int',
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => ['test_field' => ['test_field']],
    ],
    'validation' => [
      '$and' => [
        ['id' => ['$type' => 'int']],
        ['id' => ['$exists' => TRUE]],
        ['test_field' => ['$type' => 'int']],
        ['test_field' => ['$exists' => TRUE]],
      ],
    ],
    'indexes' => [
      ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
      ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
      ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
    ],
  ];

  /**
   * An array with the test_table5: name, specification and validation data.
   *
   * @var array
   */
  CONST TEST_TABLE5 = [
    'name' => 'test_table5',
    'schema' => [
      'description' => 'Schema table with no indexes.',
      'fields' => [
        'id'  => [
          'type' => 'serial',
        ],
        'test_field_string'  => [
          'type' => 'varchar',
          'length' => 20,
          'not null' => TRUE,
          'default' => "'\"Quoting fun!'\"",
        ],
      ],
    ],
    'validation' => [
      '$and' => [
        ['id' => ['$type' => 'int']],
        ['id' => ['$exists' => TRUE]],
        ['id' => ['$gte' => 0]],
        ['test_field_string' => ['$type' => 'string']],
        ['test_field_string' => ['$exists' => TRUE]],
      ],
    ],
    'indexes' => [
      ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
    ],
  ];

  /**
   * An array with the test_table6: name, specification and validation data.
   *
   * @var array
   */
  CONST TEST_TABLE6 = [
    'name' => 'test_table6',
    'schema' => [
      'description' => 'Schema table with primary and index.',
      'fields' => [
        'id'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'test_field_string'  => [
          'type' => 'varchar',
        ],
      ],
      'primary key' => ['id'],
      'indexes' => ['test_field_string' => ['test_field_string']],
    ],
    'validation' => [
      '$and' => [
        ['id' => ['$type' => 'int']],
        ['id' => ['$exists' => TRUE]],
        [
          '$or' => [
            ['test_field_string' => ['$type' => 'string']],
            ['test_field_string' => ['$exists' => FALSE]],
          ],
        ],
      ],
    ],
    'indexes' => [
      ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
      ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
      ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
    ],
  ];

  /**
   * The schema object for this connection.
   *
   * @var \Drupal\Core\Database\Schema
   */
  protected $schema;

  /**
   * The MongoDB table information service.
   *
   * @var \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   */
  protected $tableInformation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->schema = $this->container->get('database')->schema();
    $this->tableInformation = $this->container->get('database')->tableInformation();
  }

  /**
   * Helper method to test if the table validation.
   *
   * @covers ::getTableValidation
   * @covers ::getTableValidationFromDatabase
   *
   * @param string $table_name
   *   The table name.
   * @param array|null $table_validation
   *   (optional) The table validation.
   */
  protected function checkTableValidation($table_name, $table_validation = []) {
    $generated_validation = $this->schema->getTableValidation($table_name);

    $this->assertEquals($generated_validation, $table_validation, 'The expected validation is the same as the generated validation.');

    $database_validation = $this->schema->getTableValidationFromDatabase($table_name);

    $this->assertEquals($database_validation, $table_validation, 'The expected validation is the same as the validation from the database.');
  }

  /**
   * Helper method to test if the table schema is as expected.
   *
   * @covers ::getTableSchema
   *
   * @param string $table_name
   *   The table name.
   * @param array|null $table_schema
   *   (optional) The table schema.
   */
  protected function checkTableSchema($table_name, $table_schema = NULL) {
    $schema_from_table = $this->schema->getTableSchema($table_name);

    $this->assertEquals($schema_from_table, $table_schema, 'The expected table schema is the same as the saved table schema.');
  }

  /**
   * Helper method to test if all base table schema indexes exist.
   *
   * @covers ::getTableIndexFromDatabase
   * @covers ::constraintExists
   * @covers ::indexExists
   *
   * @param string $table_name
   *   The table name.
   * @param array|null $table_schema
   *   (optional) The table schema.
   */
  protected function checkTableIndexes($table_name, $table_schema = NULL) {
    // Test the primary key
    if (!empty($table_schema['primary key'])) {
      $this->assertTrue($this->schema->constraintExists($table_name, 'pkey'), 'The primary key index does exists for the table.');

      $primary_key = $this->schema->getTableIndexFromDatabase($table_name, 'pkey');
      $this->assertInstanceOf(IndexInfo::class, $primary_key);

      $this->checkIndexFields($table_schema['primary key'], $primary_key->getKey());
    }
    else {
      $this->assertFalse($this->schema->constraintExists($table_name, 'pkey'), 'The primary key index does not exist for the table.');
    }

    // Test the table unique keys.
    if (!empty($table_schema['unique keys']) && is_array($table_schema['unique keys'])) {
      foreach ($table_schema['unique keys'] as $unique_key_name => $unique_key_fields) {
        $this->assertTrue($this->schema->constraintExists($table_name, $unique_key_name . '__key'), 'The unique key index exists for the table.');

        $unique_key = $this->schema->getTableIndexFromDatabase($table_name, $unique_key_name . '__key');
        $this->assertInstanceOf(IndexInfo::class, $unique_key);

        $this->checkIndexFields($unique_key_fields, $unique_key->getKey());
      }
    }

    // Test the table indexes.
    if (!empty($table_schema['indexes']) && is_array($table_schema['indexes'])) {
      foreach ($table_schema['indexes'] as $index_name => $index_fields) {
        $this->assertTrue($this->schema->constraintExists($table_name, $index_name . '__idx'), 'The index exists for the table.');
        $this->assertTrue($this->schema->indexExists($table_name, $index_name), 'The index exists for the table.');

        $index = $this->schema->getTableIndexFromDatabase($table_name, $index_name . '__idx');
        $this->assertInstanceOf(IndexInfo::class, $index);

        $this->checkIndexFields($index_fields, $index->getKey());
      }
    }
  }

  /**
   * Helper method to test if all embedded table schema indexes exist.
   *
   * @covers ::getTableIndexFromDatabase
   * @covers ::constraintExists
   * @covers ::indexExists
   *
   * @param string $parent_table_name
   *   The parent table name.
   * @param string $embedded_table_name
   *   The embedded table name.
   * @param array $embedded_table_schema
   *   The embedded table schema.
   */
  protected function checkEmbeddedTableIndexes($parent_table_name, $embedded_table_name, $embedded_table_schema) {
    // Make sure the table information gets reloaded from the database.
    $this->tableInformation->load(TRUE);

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($embedded_table_name);

    if (!$this->schema->tableExists($embedded_table_name)) {
      // A non existent embedded table has no indexes. There is no need to
      // check if those non existed indexes exist in the database. The helper
      // method $this->checkExpectedIndexesAgainstDatabase() will check if all
      // indexes are needed according to the schema.
      return;
    }

    // Get the base table.
    $base_table_name = $this->tableInformation->getTableBaseTable($parent_table_name);
    if (empty($base_table_name) || !$this->schema->tableExists($base_table_name)) {
      // There is no base table.
      $this->fail("The parent table ($parent_table_name) and its parent table are both not a base table.");
    }

    // Test the primary key
    $constraint = $embedded_full_path . '__pkey';
    if (!empty($embedded_table_schema['primary key'])) {
      $this->assertTrue($this->schema->constraintExists($base_table_name, $constraint), 'The primary key index does exists for the embedded table.');

      $primary_key = $this->schema->getTableIndexFromDatabase($base_table_name, $constraint);
      $this->assertInstanceOf(IndexInfo::class, $primary_key);

      $embedded_primary_key_fields = [];
      foreach ($embedded_table_schema['primary key'] as $field) {
        $embedded_primary_key_fields[] = $embedded_full_path . $field;
      }
      $this->checkIndexFields($embedded_primary_key_fields, $primary_key->getKey());
    }
    else {
      $this->assertFalse($this->schema->constraintExists($base_table_name, $constraint), 'The primary key index does not exist for the embedded table.');
    }

    // Test the table unique keys.
    if (!empty($embedded_table_schema['unique keys']) && is_array($embedded_table_schema['unique keys'])) {
      foreach ($embedded_table_schema['unique keys'] as $unique_key_name => $unique_key_fields) {
        $constraint = $embedded_full_path . $unique_key_name . '__key';
        $this->assertTrue($this->schema->constraintExists($base_table_name, $constraint), 'The unique key index exists for the embedded table.');

        $unique_key = $this->schema->getTableIndexFromDatabase($base_table_name, $constraint);
        $this->assertInstanceOf(IndexInfo::class, $unique_key);

        $embedded_unique_key_fields = [];
        foreach ($unique_key_fields as $unique_key_field) {
          $embedded_unique_key_fields[] = $embedded_full_path . $unique_key_field;
        }
        $this->checkIndexFields($embedded_unique_key_fields, $unique_key->getKey());
      }
    }

    // Test the table indexes.
    if (!empty($embedded_table_schema['indexes']) && is_array($embedded_table_schema['indexes'])) {
      foreach ($embedded_table_schema['indexes'] as $index_name => $index_fields) {
        $constraint = $embedded_full_path . $index_name . '__idx';
        $this->assertTrue($this->schema->constraintExists($base_table_name, $constraint), 'The index exists for the table.');
        $this->assertTrue($this->schema->indexExists($embedded_table_name, $index_name), 'The index exists for the table.');

        $index = $this->schema->getTableIndexFromDatabase($base_table_name, $constraint);
        $this->assertInstanceOf(IndexInfo::class, $index);

        $embedded_index_fields = [];
        foreach ($index_fields as $index_field) {
          $embedded_index_fields[] = $embedded_full_path . $index_field;
        }
        $this->checkIndexFields($embedded_index_fields, $index->getKey());
      }
    }
  }

  /**
   * Helper method to test if all schema index fields exists.
   *
   * @param array $schema_fields
   *   The schema fields for the index to test.
   * @param array $database_fields
   *   The database fields for the index to test.
   */
  protected function checkIndexFields(array $schema_fields, array $database_fields) {
    // Test if all schema index fields exists in the database index.
    $database_field_keys = array_keys($database_fields);
    foreach ($schema_fields as $schema_field) {
      $this->assertTrue(in_array($schema_field, $database_field_keys, TRUE), 'The schema index field exists in the database index');
    }

    // Test if all database index fields exists in the schema index.
    $schema_field_values = array_values($schema_fields);
    foreach ($database_fields as $database_field_key => $database_field_value) {
      $this->assertTrue(in_array($database_field_key, $schema_field_values, TRUE), 'The database index field exists in the schema index');
    }
  }

  /**
   * Helper method to test the number of indexes from the schema data is the same as the number of indexes in the database.
   *
   * @covers ::getTableIndexesFromDatabase
   *
   * @param array $base_table_data
   *   The base table data
   * @param array $embedded_tables_data
   *   The embedded table data.
   */
  protected function checkTableNumberOfIndexes($base_table_data, $embedded_tables_data = []) {
    $indexes = $this->schema->getTableIndexesFromDatabase($base_table_data['name']);

    $index_count = 0;
    if (!empty($base_table_data['schema']['primary key'])) {
      $index_count++;
    }
    if (!empty($base_table_data['schema']['unique keys']) && is_array($base_table_data['schema']['unique keys'])) {
      $index_count += count($base_table_data['schema']['unique keys']);
    }
    if (!empty($base_table_data['schema']['indexes']) && is_array($base_table_data['schema']['indexes'])) {
      $index_count += count($base_table_data['schema']['indexes']);
    }

    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if (!empty($embedded_table_data['schema']['primary key'])) {
          $index_count++;
        }
        if (!empty($embedded_table_data['schema']['unique keys']) && is_array($embedded_table_data['schema']['unique keys'])) {
          $index_count += count($embedded_table_data['schema']['unique keys']);
        }
        if (!empty($embedded_table_data['schema']['indexes']) && is_array($embedded_table_data['schema']['indexes'])) {
          $index_count += count($embedded_table_data['schema']['indexes']);
        }
      }
    }

    if ($this->schema->tableExists($base_table_data['name'])) {
      // MongoDB also creates an index for the field "_id".
      $this->assertEquals(count($indexes) - 1, $index_count, 'The number of indexes from the table schema is the same as number of indexes from the database.');
    }
  }

  /**
   * Helper method to test if all expected indexes are on the given table.
   *
   * @param string $table
   *   The table name to check for the expected indexes.
   * @param array $expected_indexes
   *   An array of arrays, with the inner array having the data for an expected index (name and key).
   */
  protected function checkExpectedIndexesAgainstDatabase($table, $expected_indexes) {
    $database_indexes = $this->schema->getTableIndexesFromDatabase($table);

    // Check that all expected indexes exist in the database.
    foreach ($expected_indexes as $expected_index) {
      $index_exists = FALSE;
      foreach ($database_indexes as $database_index) {
        if ($database_index->getName() == $expected_index['name']) {
          $this->assertEquals($database_index->getKey(), $expected_index['key'], 'The index key is the same as the expected key.');
          $this->assertEquals($database_index->isUnique(), $expected_index['unique'], 'The index uniqueness is the same as the expected uniqueness.');
          $index_exists = TRUE;
        }
      }
      $this->assertTrue($index_exists, 'The expected index exist in the database.');
    }

    // Check that all indexes in the database are in the list of expected
    // indexes.
    foreach ($database_indexes as $database_index) {
      $index_exists = FALSE;
      foreach ($expected_indexes as $expected_index) {
        if ($database_index->getName() == $expected_index['name']) {
          $this->assertEquals($database_index->getKey(), $expected_index['key'], 'The index key is the same as the expected key.');
          $this->assertEquals($database_index->isUnique(), $expected_index['unique'], 'The index uniqueness is the same as the expected uniqueness.');
          $index_exists = TRUE;
        }
      }
      $this->assertTrue($index_exists, 'The index exist in the database is on the list with expected indexes.');
    }

    // Check the number of indexes in the database is the same as the number of
    // expected indexes.
    $this->assertEquals(count($database_indexes), count($expected_indexes), 'The number indexes in the database is the same as the number of expected indexes.');
  }

}
