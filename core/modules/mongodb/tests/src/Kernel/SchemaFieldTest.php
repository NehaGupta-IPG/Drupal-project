<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel;

// phpcs:ignoreFile

/**
 * Tests MongoDB base/embedded field creation and modification via the schema API.
 *
 * @group MongoDB
 * @coversDefaultClass \Drupal\mongodb\Driver\Database\mongodb\Schema
 */
class SchemaFieldTest extends SchemaTestBase {

  /**
   * An array with the test_field1: name, specification and keys data.
   *
   * @var array
   */
  CONST TEST_FIELD1 = [
    'name' => 'test_field1',
    'spec' => [
      'type' => 'int',
      'not null' => TRUE,
    ],
    'keys' => [
      'unique keys' => ['test_field1' => ['test_field1']],
    ],
  ];

  /**
   * An array with the test_field2: name, specification and keys data.
   *
   * @var array
   */
  CONST TEST_FIELD2 = [
    'name' => 'test_field2',
    'spec' => [
      'type' => 'varchar',
      'length' => 20,
      'not null' => TRUE,
      'default' => "'\"Quoting fun!'\"",
    ],
    'keys' => [
      'indexes' => ['test_field2' => ['test_field2']],
    ],
  ];

  /**
   * An array with the test_field3: name, specification and keys data.
   *
   * @var array
   */
  CONST TEST_FIELD3 = [
    'name' => 'test_field3',
    'spec' => [
      'type' => 'int',
      'not null' => TRUE,
    ],
    'keys' => [
      'primary key' => ['test_field3'],
    ],
  ];

  /**
   * @covers ::fieldExists
   */
  public function testFieldExists() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE1['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE2['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE1['name'], static::TEST_TABLE1['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE1['name']), 'The table exists in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE2['name']), 'The table does not exist in the MongoDB database.');

    // Test that if a field that exists on an existing table will return TRUE.
    $this->assertTrue($this->schema->fieldExists(static::TEST_TABLE1['name'], 'test_field'), "The table 'test_table1' has a field with the name 'test_field'.");

    // Test that if a non-existing field on an existing table will return FALSE.
    $this->assertFalse($this->schema->fieldExists(static::TEST_TABLE1['name'], 'does_not_exists_field'), "The table 'test_table1' does not have a field with the name 'does_not_exists_field'.");

    // Test that a  non-existing table will return FALSE.
    $this->assertFalse($this->schema->fieldExists(static::TEST_TABLE2['name'], 'test_field'), "The table 'test_table1' has a field with the name 'test_field'.");
  }

  /**
   * Data provider for testEmbeddedFieldExists().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the field data to add a field to the table (the name, the
   *       specification and the optional keys/indexes)
   *     - the array of expected data. The schema key hold the expected schema
   *       data of the table with the added field. The validation key holds the
   *       expected validation of the table with the added field.
   */
  public static function providerEmbeddedFieldExists() {
    return [
      [static::TEST_TABLE2['name'], 'test_field_int_null', TRUE],
      [static::TEST_TABLE2['name'], 'does_not_exists_field', FALSE],
      [static::TEST_TABLE2['name'], 'test_field_int_null', TRUE],
      ['does_not_exist_embedded_table', 'test_field_int_null', FALSE],
      [static::TEST_TABLE6['name'], 'test_field_varchar_null', FALSE],
      [static::TEST_TABLE3['name'], 'id', TRUE],
      [static::TEST_TABLE3['name'], 'does_not_exists_field', FALSE],
      [static::TEST_TABLE4['name'], 'test_field', TRUE],
      [static::TEST_TABLE4['name'], 'does_not_exists_field', FALSE],
    ];
  }

  /**
   * @covers ::fieldExists
   * @dataProvider providerEmbeddedFieldExists
   */
  public function testEmbeddedFieldExists($embedded_table_name, $field_name, $expected_result) {
    $embedded_tables_data = [
      static::TEST_TABLE1['name'] => [static::TEST_TABLE2],
      static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4],
      static::TEST_TABLE3['name'] => [static::TEST_TABLE5],
    ];

    // Create all the tables.
    $this->schema->createTable(static::TEST_TABLE1['name'], static::TEST_TABLE1['schema']);
    $this->schema->createTable(static::TEST_TABLE6['name'], static::TEST_TABLE6['schema']);
    foreach ($embedded_tables_data as $parent_table => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->schema->createEmbeddedTable($parent_table, $embedded_table_data['name'], $embedded_table_data['schema']);
      }
    }

    // Test if all tables are created.
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE1['name']), 'The table "test_table1" exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE2['name']), 'The table "test_table2" exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE3['name']), 'The table "test_table3" exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table "test_table4" exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table "test_table5" exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE6['name']), 'The table "test_table6" exists in the MongoDB database.');

    $result = $this->schema->fieldExists($embedded_table_name, $field_name);
    if ($expected_result) {
      $this->assertTrue($result, 'The field should exist on the table.');
    }
    else {
      $this->assertFalse($result, 'The field should not exist on the table.');
    }
  }

  /**
   * Data provider for testAddField().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the array of table data to use as embedded tables (the name, the
   *       schema and the validation).
   *     - the field data to add a field to an embedded table (the name, the
   *       specification and the optional keys/indexes)
   *     - the array of expected data. The schema key hold the expected schema
   *       data of the embedded table with the added field. The validation key
   *       holds the expected validation of the base table with the added
   *       embedded table and the added field.
   */
  public static function providerAddField() {
    $test_table4_with_test_field1 = [
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
          'test_field1' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_field' => ['test_field'],
          'test_field1' => ['test_field1'],
        ],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          ['test_field1' => ['$type' => 'int']],
          ['test_field1' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_field1__key', 'unique' => TRUE, 'key' => ['test_field1' => 1]],
      ],
    ];

    $test_table5_with_test_field1 = [
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
          'test_field1' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => [
          'test_field1' => ['test_field1'],
        ],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          ['test_field1' => ['$type' => 'int']],
          ['test_field1' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_field1__key', 'unique' => TRUE, 'key' => ['test_field1' => 1]],
      ],
    ];

    $test_table6_with_test_field1 = [
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
          'test_field1' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_field1' => ['test_field1']],
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
          ['test_field1' => ['$type' => 'int']],
          ['test_field1' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_field1__key', 'unique' => TRUE, 'key' => ['test_field1' => 1]],
      ],
    ];

    $test_table4_with_test_field2 = [
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
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_field' => ['test_field']],
        'indexes' => ['test_field2' => ['test_field2']],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          ['test_field2' => ['$type' => 'string']],
          ['test_field2' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_field2__idx', 'unique' => FALSE, 'key' => ['test_field2' => 1]],
      ],
    ];

    $test_table5_with_test_field2 = [
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
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'indexes' => ['test_field2' => ['test_field2']],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          ['test_field2' => ['$type' => 'string']],
          ['test_field2' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_field2__idx', 'unique' => FALSE, 'key' => ['test_field2' => 1]],
      ],
    ];

    $test_table6_with_test_field2 = [
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
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
        'indexes' => [
          'test_field_string' => ['test_field_string'],
          'test_field2' => ['test_field2'],
        ],
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
          ['test_field2' => ['$type' => 'string']],
          ['test_field2' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_field2__idx', 'unique' => FALSE, 'key' => ['test_field2' => 1]],
      ],
    ];

    $test_table4_with_test_field3 = [
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
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'unique keys' => ['test_field' => ['test_field']],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          ['test_field3' => ['$type' => 'int']],
          ['test_field3' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field3' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
      ],
    ];

    $test_table5_with_test_field3 = [
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
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          ['test_field3' => ['$type' => 'int']],
          ['test_field3' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field3' => 1]],
      ],
    ];

    $test_table6_with_test_field3 = [
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
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
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
          ['test_field3' => ['$type' => 'int']],
          ['test_field3' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field3' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
      ],
    ];

    $base_table_4_with_embedded_table_5_and_field_1 = [
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
          'test_field1' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => [
          'test_field1' => ['test_field1'],
        ],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                  ['test_table5.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                  ['test_table5.test_field1' => ['$type' => 'int']],
                  ['test_table5.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.test_field1__key', 'unique' => TRUE, 'key' => ['test_table5.test_field1' => 1]],
      ],
    ];

    $base_table_4_with_embedded_table_5_and_6_and_field_1 = [
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
          'test_field1' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => [
          'test_field1' => ['test_field1'],
        ],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                  ['test_table5.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                  ['test_table5.test_field1' => ['$type' => 'int']],
                  ['test_table5.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.test_field1__key', 'unique' => TRUE, 'key' => ['test_table5.test_field1' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $base_table_4_with_embedded_table_6_and_5_and_field_1 = [
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
          'test_field1' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_field1' => ['test_field1']],
        'indexes' => ['test_field_string' => ['test_field_string']],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                  ['test_table6.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table6.test_field1' => ['$type' => 'int']],
                  ['test_table6.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
        ['name' => 'test_table6.test_field1__key', 'unique' => TRUE, 'key' => ['test_table6.test_field1' => 1]],
      ],
    ];

    $base_table_5_with_embedded_table_6_and_field_2 = [
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
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
        'indexes' => [
          'test_field_string' => ['test_field_string'],
          'test_field2' => ['test_field2'],
        ],
        'embedded_to_table' => 'test_table5',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                  ['test_table6.test_field2' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table6.test_field2' => ['$type' => 'string']],
                  ['test_table6.test_field2' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
        ['name' => 'test_table6.test_field2__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field2' => 1]],
      ],
    ];

    $base_table_5_with_embedded_table_4_and_6_and_field_2 = [
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
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_field' => ['test_field']],
        'indexes' => ['test_field2' => ['test_field2']],
        'embedded_to_table' => 'test_table5',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                  ['test_table4.test_field2' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                  ['test_table4.test_field2' => ['$type' => 'string']],
                  ['test_table4.test_field2' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
        ['name' => 'test_table4.test_field2__idx', 'unique' => FALSE, 'key' => ['test_table4.test_field2' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $base_table_5_with_embedded_table_6_and_4_and_field_2 = [
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
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
        'indexes' => [
          'test_field_string' => ['test_field_string'],
          'test_field2' => ['test_field2'],
        ],
        'embedded_to_table' => 'test_table5',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                  ['test_table6.test_field2' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table6.test_field2' => ['$type' => 'string']],
                  ['test_table6.test_field2' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
        ['name' => 'test_table6.test_field2__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field2' => 1]],
      ],
    ];

    $base_table_6_with_embedded_table_5_and_field_3 = [
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
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'embedded_to_table' => 'test_table6',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                  ['test_table5.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                  ['test_table5.test_field3' => ['$type' => 'int']],
                  ['test_table5.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table5.__pkey', 'unique' => TRUE, 'key' => ['test_table5.test_field3' => 1]],
      ],
    ];

    $base_table_6_with_embedded_table_5_and_4_and_field_3 = [
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
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'embedded_to_table' => 'test_table6',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                  ['test_table5.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                  ['test_table5.test_field3' => ['$type' => 'int']],
                  ['test_table5.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
        ['name' => 'test_table5.__pkey', 'unique' => TRUE, 'key' => ['test_table5.test_field3' => 1]],
      ],
    ];

    $base_table_6_with_embedded_table_4_and_5_and_field_3 = [
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
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'unique keys' => ['test_field' => ['test_field']],
        'embedded_to_table' => 'test_table6',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                  ['test_table4.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                  ['test_table4.test_field3' => ['$type' => 'int']],
                  ['test_table4.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.test_field3' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_1_on_table_1 = [
      'schema' => [
        'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'default' => NULL,
            'not null' => TRUE,
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
          'test_field1' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_field' => ['test_field'],
          'test_field1' => ['test_field1'],
        ],
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
          ['test_field1' => ['$type' => 'int']],
          ['test_field1' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$type' => 'int']],
                  ['test_table2.test_table4.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_field1__key', 'unique' => TRUE, 'key' => ['test_field1' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_1_on_table_5 = [
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
          'test_field1' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => ['test_field1' => ['test_field1']],
        'embedded_to_table' => 'test_table3',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.test_field1' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$type' => 'int']],
                  ['test_table2.test_table4.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table5.test_field1__key', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table5.test_field1' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_3_on_table_5 = [
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
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'embedded_to_table' => 'test_table3',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.test_field3' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$type' => 'int']],
                  ['test_table2.test_table4.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table5.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table5.test_field3' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_1_on_table_6 = [
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
          'test_field1' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_field1' => ['test_field1']],
        'indexes' => ['test_field_string' => ['test_field_string']],
        'embedded_to_table' => 'test_table3',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_table6.test_field1' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$type' => 'int']],
                  ['test_table2.test_table4.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field1__key', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.test_field1' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_3_on_table_6 = [
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
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'indexes' => ['test_field_string' => ['test_field_string']],
        'embedded_to_table' => 'test_table3',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_table6.test_field3' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$type' => 'int']],
                  ['test_table2.test_table4.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.test_field3' => 1]],
      ],
    ];

    return [
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        static::TEST_FIELD1,
        $test_table4_with_test_field1,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        static::TEST_FIELD1,
        $test_table5_with_test_field1,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        static::TEST_FIELD1,
        $test_table6_with_test_field1,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        static::TEST_FIELD2,
        $test_table4_with_test_field2,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        static::TEST_FIELD2,
        $test_table5_with_test_field2,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        static::TEST_FIELD2,
        $test_table6_with_test_field2,
      ],

      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        static::TEST_FIELD3,
        $test_table4_with_test_field3,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        static::TEST_FIELD3,
        $test_table5_with_test_field3,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        static::TEST_FIELD3,
        $test_table6_with_test_field3,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        static::TEST_FIELD1,
        $base_table_4_with_embedded_table_5_and_field_1,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE5['name'],
        static::TEST_FIELD1,
        $base_table_4_with_embedded_table_5_and_6_and_field_1,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE6, static::TEST_TABLE5]],
        static::TEST_TABLE6['name'],
        static::TEST_FIELD1,
        $base_table_4_with_embedded_table_6_and_5_and_field_1,
      ],
      [
        static::TEST_TABLE5,
        [static::TEST_TABLE5['name'] => [static::TEST_TABLE6]],
        static::TEST_TABLE6['name'],
        static::TEST_FIELD2,
        $base_table_5_with_embedded_table_6_and_field_2,
      ],
      [
        static::TEST_TABLE5,
        [static::TEST_TABLE5['name'] => [static::TEST_TABLE4, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        static::TEST_FIELD2,
        $base_table_5_with_embedded_table_4_and_6_and_field_2,
      ],
      [
        static::TEST_TABLE5,
        [static::TEST_TABLE5['name'] => [static::TEST_TABLE6, static::TEST_TABLE4]],
        static::TEST_TABLE6['name'],
        static::TEST_FIELD2,
        $base_table_5_with_embedded_table_6_and_4_and_field_2,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        static::TEST_FIELD3,
        $base_table_6_with_embedded_table_5_and_field_3,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE5, static::TEST_TABLE4]],
        static::TEST_TABLE5['name'],
        static::TEST_FIELD3,
        $base_table_6_with_embedded_table_5_and_4_and_field_3,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4, static::TEST_TABLE5]],
        static::TEST_TABLE4['name'],
        static::TEST_FIELD3,
        $base_table_6_with_embedded_table_4_and_5_and_field_3,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE1['name'],
        static::TEST_FIELD1,
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_1_on_table_1,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE5['name'],
        static::TEST_FIELD1,
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_1_on_table_5,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE5['name'],
        static::TEST_FIELD3,
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_3_on_table_5,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE6['name'],
        static::TEST_FIELD1,
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_1_on_table_6,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE6['name'],
        static::TEST_FIELD3,
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_3_on_table_6,
      ],
    ];
  }

  /**
   * @covers ::addField
   * @covers ::fieldExists
   * @dataProvider providerAddField
   */
  public function testAddField($base_table_data, $embedded_tables_data, $table_name_to_add_field, $field_data, $base_table_with_embedded_tables_and_added_field) {
    $this->assertFalse($this->schema->tableExists($base_table_data['name']), 'The table does not exist in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertFalse($this->schema->tableExists($embedded_table_data['name']), 'The embedded table does not exist in the MongoDB database.');
      }
    }
    $this->assertFalse($this->schema->fieldExists($table_name_to_add_field, $field_data['name']), 'The field does not exist on the embedded table in the MongoDB database.');

    // Create the base table, the embedded tables and add the field.
    $this->schema->createTable($base_table_data['name'], $base_table_data['schema']);
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->schema->createEmbeddedTable($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
      }
    }

    // Call the to be tested method: Schema::addField().
    $this->schema->addField($table_name_to_add_field, $field_data['name'], $field_data['spec'], $field_data['keys']);

    $this->assertTrue($this->schema->tableExists($base_table_data['name']), 'The table exists in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertTrue($this->schema->tableExists($embedded_table_data['name']), 'The embedded table exists in the MongoDB database.');
      }
    }
    $this->assertTrue($this->schema->fieldExists($table_name_to_add_field, $field_data['name']), 'The field exists on the table in the MongoDB database.');

    // Check that the validation, schema or index data are what they should be.
    $this->checkTableValidation($base_table_data['name'], $base_table_with_embedded_tables_and_added_field['validation']);
    if ($base_table_data['name'] == $table_name_to_add_field) {
      $this->checkTableSchema($base_table_data['name'], $base_table_with_embedded_tables_and_added_field['schema']);
    }
    else {
      $this->checkTableSchema($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_add_field) {
          $this->checkTableSchema($table_name_to_add_field, $base_table_with_embedded_tables_and_added_field['schema']);
        }
        else {
          $embedded_table_data['schema']['embedded_to_table'] = $parent_table_name;
          $this->checkTableSchema($embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    if ($base_table_data['name'] == $table_name_to_add_field) {
      $this->checkTableIndexes($base_table_data['name'], $base_table_with_embedded_tables_and_added_field['schema']);
    }
    else {
      $this->checkTableIndexes($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_add_field) {
          $this->checkEmbeddedTableIndexes($parent_table_name, $table_name_to_add_field, $base_table_with_embedded_tables_and_added_field['schema']);
        }
        else {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }

    // Update the embedded_tables_data with adding field to a specific embedded
    // table.
    if ($base_table_data['name'] == $table_name_to_add_field) {
      $base_table_data['schema'] = $base_table_with_embedded_tables_and_added_field['schema'];
    }
    foreach ($embedded_tables_data as $parent_table_name => &$embedded_table_array) {
      foreach ($embedded_table_array as &$embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_add_field) {
          $embedded_table_data['schema'] = $base_table_with_embedded_tables_and_added_field['schema'];
        }
      }
    }
    $this->checkExpectedIndexesAgainstDatabase($base_table_data['name'], $base_table_with_embedded_tables_and_added_field['indexes']);
    $this->checkTableNumberOfIndexes($base_table_data, $embedded_tables_data);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldForTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    // If we try to add a field on a non existent base table an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->addField(static::TEST_TABLE4['name'], static::TEST_FIELD1['name'], static::TEST_FIELD1['spec'], static::TEST_FIELD1['keys']);
  }

  /**
   * @covers ::addField
   * @covers ::fieldExists
   */
  public function testAddFieldForNewFieldExists() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->fieldExists(static::TEST_TABLE4['name'], static::TEST_FIELD1['name']), 'The table field does not exist in the MongoDB database.');

    // Create the table and add the field to the table.
    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);
    $this->schema->addField(static::TEST_TABLE4['name'], static::TEST_FIELD1['name'], static::TEST_FIELD1['spec'], static::TEST_FIELD1['keys']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->fieldExists(static::TEST_TABLE4['name'], static::TEST_FIELD1['name']), 'The table field exists in the MongoDB database.');

    // If we try to add a field that exists on base table an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addField(static::TEST_TABLE4['name'], static::TEST_FIELD1['name'], static::TEST_FIELD1['spec'], static::TEST_FIELD1['keys']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldForEmbeddedTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    // Create the to be renamed table.
    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');

    // If we try to rename an embedded table on a non existent embedded table an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->addField(static::TEST_TABLE5['name'], static::TEST_FIELD1['name'], static::TEST_FIELD1['spec'], static::TEST_FIELD1['keys']);
  }

  /**
   * @covers ::addField
   */
  public function testAddFieldForNewEmbeddedFieldExists() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The embedded table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->fieldExists(static::TEST_TABLE5['name'], static::TEST_FIELD1['name']), 'The field on the embedded table does not exist in the MongoDB database.');

    // Create the to be renamed tables.
    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE4['name'], static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);
    $this->schema->addField(static::TEST_TABLE5['name'], static::TEST_FIELD1['name'], static::TEST_FIELD1['spec'], static::TEST_FIELD1['keys']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The embedded table exists in the MongoDB database.');
    $this->assertTrue($this->schema->fieldExists(static::TEST_TABLE5['name'], static::TEST_FIELD1['name']), 'The field on the embedded table exists in the MongoDB database.');

    // If we try to rename an embedded table to an existent embedded table an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addField(static::TEST_TABLE5['name'], static::TEST_FIELD1['name'], static::TEST_FIELD1['spec'], static::TEST_FIELD1['keys']);
  }

  /**
   * Data provider for testDropField().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the array of table data to use as embedded tables (the name, the
   *       schema and the validation).
   *     - the field name for field to be dropped from the first embedded table.
   *     - the array of expected data. The schema key hold the expected schema
   *       data of the embedded table without the dropped field. The validation
   *       key holds the expected validation of the base table with all embedded
   *       tables and without the dropped field.
   */
  public static function providerDropField() {
    $test_table4_without_id = [
      'name' => 'test_table4',
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => ['test_field' => ['test_field']],
      ],
      'validation' => [
        '$and' => [
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
      ],
    ];

    $test_table4_without_test_field = [
      'name' => 'test_table4',
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
      ],
    ];

    $test_table5_without_id = [
      'name' => 'test_table5',
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
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
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
      ],
    ];

    $test_table5_without_test_field_string = [
      'name' => 'test_table5',
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id'  => [
            'type' => 'serial',
          ],
        ],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
      ],
    ];

    $test_table6_without_id = [
      'name' => 'test_table6',
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
          ],
        ],
        'indexes' => ['test_field_string' => ['test_field_string']],
      ],
      'validation' => [
        '$and' => [
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
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
      ],
    ];

    $test_table6_without_test_field_string = [
      'name' => 'test_table6',
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
      ],
    ];

    $base_4_embedded_5_without_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
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

    $base_4_embedded_5_and_6_without_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $base_4_embedded_5_and_6_without_test_field_string = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id'  => [
            'type' => 'serial',
          ],
        ],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $base_4_embedded_6_and_5_without_id = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
          ],
        ],
        'indexes' => ['test_field_string' => ['test_field_string']],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $base_4_embedded_6_and_5_without_test_field_string = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
      ],
    ];

    $base_5_embedded_4_and_6_without_id = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => ['test_field' => ['test_field']],
        'embedded_to_table' => 'test_table5',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $base_5_embedded_4_and_6_without_test_field = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'embedded_to_table' => 'test_table5',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $base_6_embedded_4_and_5_without_id = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => ['test_field' => ['test_field']],
        'embedded_to_table' => 'test_table6',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
      ],
    ];

    $base_6_embedded_4_and_5_without_test_field = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'embedded_to_table' => 'test_table6',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_without_test_field_on_table_1 = [
      'schema' => [
        'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'default' => NULL,
            'not null' => TRUE,
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
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              ['test_field_string_ascii' => ['$type' => 'string']],
              ['test_field_string_ascii' => ['$exists' => FALSE]],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$type' => 'int']],
                  ['test_table2.test_table4.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_without_test_field_int_null_on_table_2 = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id'  => [
            'type' => 'serial',
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
        ],
        'indexes' => [
          'test_fields_default' => ['test_field_int_default', 'test_field_float_default', 'test_field_numeric_default'],
        ],
        'embedded_to_table' => 'test_table1',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$type' => 'int']],
                  ['test_table2.test_table4.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_without_test_field_on_table_4 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'embedded_to_table' => 'test_table2',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$type' => 'int']],
                  ['test_table2.test_table4.id' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_without_id_on_table_5 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'embedded_to_table' => 'test_table3',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$type' => 'int']],
                  ['test_table2.test_table4.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_without_id_on_table_6 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
          ],
        ],
        'indexes' => ['test_field_string' => ['test_field_string']],
        'embedded_to_table' => 'test_table3',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$type' => 'int']],
                  ['test_table2.test_table4.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
      ],
    ];

    return [
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'id',
        $test_table4_without_id,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'test_field',
        $test_table4_without_test_field,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'id',
        $test_table5_without_id,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'test_field_string',
        $test_table5_without_test_field_string,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        'id',
        $test_table6_without_id,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        'test_field_string',
        $test_table6_without_test_field_string,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        'id',
        $base_4_embedded_5_without_id,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE5['name'],
        'id',
        $base_4_embedded_5_and_6_without_id,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE5['name'],
        'test_field_string',
        $base_4_embedded_5_and_6_without_test_field_string,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE6, static::TEST_TABLE5]],
        static::TEST_TABLE6['name'],
        'id',
        $base_4_embedded_6_and_5_without_id,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE6, static::TEST_TABLE5]],
        static::TEST_TABLE6['name'],
        'test_field_string',
        $base_4_embedded_6_and_5_without_test_field_string,
      ],
      [
        static::TEST_TABLE5,
        [static::TEST_TABLE5['name'] => [static::TEST_TABLE4, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        'id',
        $base_5_embedded_4_and_6_without_id,
      ],
      [
        static::TEST_TABLE5,
        [static::TEST_TABLE5['name'] => [static::TEST_TABLE4, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        'test_field',
        $base_5_embedded_4_and_6_without_test_field,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4, static::TEST_TABLE5]],
        static::TEST_TABLE4['name'],
        'id',
        $base_6_embedded_4_and_5_without_id,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4, static::TEST_TABLE5]],
        static::TEST_TABLE4['name'],
        'test_field',
        $base_6_embedded_4_and_5_without_test_field,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE1['name'],
        'test_field',
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_without_test_field_on_table_1,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE2['name'],
        'test_field_int_null',
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_without_test_field_int_null_on_table_2,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        'test_field',
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_without_test_field_on_table_4,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE5['name'],
        'id',
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_without_id_on_table_5,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE6['name'],
        'id',
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_without_id_on_table_6,
      ],
    ];
  }

  /**
   * @covers ::dropField
   * @covers ::fieldExists
   * @dataProvider providerDropField
   */
  public function testDropField($base_table_data, $embedded_tables_data, $table_name_to_drop_field, $field_name, $embedded_table_data_without_field) {
    $this->assertFalse($this->schema->tableExists($base_table_data['name']), 'The table does not exist in the MongoDB database.');

    // Create the to be base table and the embedded tables.
    $this->schema->createTable($base_table_data['name'], $base_table_data['schema']);
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->schema->createEmbeddedTable($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
      }
    }

    $this->assertTrue($this->schema->tableExists($base_table_data['name']), 'The table exists in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertTrue($this->schema->tableExists($embedded_table_data['name']), 'The embedded table exists in the MongoDB database.');
      }
    }
    $this->assertTrue($this->schema->fieldExists($table_name_to_drop_field, $field_name), 'The field exists on the embedded table in the MongoDB database.');

    // Drop the field.
    $this->schema->dropField($table_name_to_drop_field, $field_name);

    $this->assertFalse($this->schema->fieldExists($table_name_to_drop_field, $field_name), 'The field does not exist on the embedded table in the MongoDB database.');

    // Check that the validation, schema or index data is moved to the new table.
    $this->checkTableValidation($base_table_data['name'], $embedded_table_data_without_field['validation']);
    if ($base_table_data['name'] == $table_name_to_drop_field) {
      $this->checkTableSchema($base_table_data['name'], $embedded_table_data_without_field['schema']);
    }
    else {
      $this->checkTableSchema($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_field) {
          $this->checkTableSchema($embedded_table_data['name'], $embedded_table_data_without_field['schema']);
        }
        else {
          $embedded_table_data['schema']['embedded_to_table'] = $parent_table_name;
          $this->checkTableSchema($embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    if ($base_table_data['name'] == $table_name_to_drop_field) {
      $this->checkTableIndexes($base_table_data['name'], $embedded_table_data_without_field['schema']);
    }
    else {
      $this->checkTableIndexes($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_field) {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $embedded_table_data_without_field['schema']);
        }
        else {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    $this->checkExpectedIndexesAgainstDatabase($base_table_data['name'], $embedded_table_data_without_field['indexes']);

    // Update the embedded_tables_data with removing the deleted embedded table.
    if ($base_table_data['name'] == $table_name_to_drop_field) {
      $base_table_data['schema'] = $embedded_table_data_without_field['schema'];
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $key => $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_field) {
          $embedded_tables_data[$parent_table_name][$key]['schema'] = $embedded_table_data_without_field['schema'];
        }
      }
    }
    $this->checkTableNumberOfIndexes($base_table_data, $embedded_tables_data);
  }

  /**
   * @covers ::dropField
   */
  public function testDropFieldForTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    // Test that the dropField method return false when the base table does not exist.
    $this->assertFalse($this->schema->dropField(static::TEST_TABLE4['name'], 'test_field'), 'The table does not exist in the MongoDB database.');
  }

  /**
   * @covers ::dropField
   */
  public function testDropFieldForFieldDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');

    // Test that the dropField method return false when the field does not exist
    // on the base table.
    $this->assertFalse($this->schema->dropField(static::TEST_TABLE4['name'], 'does-not-exist-field'), 'The field does not exist on the table in the MongoDB database.');
  }

  /**
   * @covers ::dropField
   */
  public function testDropFieldForEmbeddedTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    // Create the to be renamed table.
    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');

    // Test that the dropField method return false when the embedded table does
    // not exist on the base table.
    $this->assertFalse($this->schema->dropField(static::TEST_TABLE5['name'], 'id'), 'The embedded table does not exist in the MongoDB database.');
  }

  /**
   * @covers ::dropField
   */
  public function testDropFieldForEmbeddedFieldDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The embedded table does not exist in the MongoDB database.');

    // Create the to be renamed table.
    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE4['name'], static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The embedded table exists in the MongoDB database.');

    // Dropping a non-existent field from an embedded table should return false.
    $this->assertFalse($this->schema->dropField(static::TEST_TABLE5['name'], 'does_not_exist_field'), 'The field does not exist on the embedded table.');
  }

  /**
   * Data provider for testChangeField().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the array of table data to use as embedded tables (the name, the
   *       schema and the validation).
   *     - the field name for the to be changed field.
   *     - the field data for replacing the old field data (the name, the
   *       specification and the optional keys/indexes)
   *     - the array of expected data. The schema key hold the expected schema
   *       data of the table with the changed field. The validation key holds
   *       the expected validation of the table with the changed field.
   */
  public static function providerChangeField() {
    $test_table4_changed_id_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => [
          'test_field' => ['test_field'],
        ],
        'primary key' => ['test_field1'],
      ],
      'validation' => [
        '$and' => [
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          ['test_field1' => ['$type' => 'int']],
          ['test_field1' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field1' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
      ],
    ];

    $test_table4_changed_test_field_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_field1' => ['test_field1']],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field1' => ['$type' => 'int']],
          ['test_field1' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field1__key', 'unique' => TRUE, 'key' => ['test_field1' => 1]],
      ],
    ];

    $test_table4_changed_id_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['test_field2'],
        'unique keys' => ['test_field' => ['test_field']],
      ],
      'validation' => [
        '$and' => [
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          ['test_field2' => ['$type' => 'string']],
          ['test_field2' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field2' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
      ],
    ];

    $test_table4_changed_test_field_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
        'indexes' => ['test_field2' => ['test_field2']],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field2' => ['$type' => 'string']],
          ['test_field2' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field2__idx', 'unique' => FALSE, 'key' => ['test_field2' => 1]],
      ],
    ];

    $test_table4_changed_id_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'unique keys' => ['test_field' => ['test_field']],
      ],
      'validation' => [
        '$and' => [
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          ['test_field3' => ['$type' => 'int']],
          ['test_field3' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field3' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
      ],
    ];

    $test_table4_changed_test_field_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field3' => ['$type' => 'int']],
          ['test_field3' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field3' => 1]],
      ],
    ];

    $test_table5_changed_id_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => ['test_field1' => ['test_field1']],
      ],
      'validation' => [
        '$and' => [
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          ['test_field1' => ['$type' => 'int']],
          ['test_field1' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_field1__key', 'unique' => TRUE, 'key' => ['test_field1' => 1]],
      ],
    ];

    $test_table5_changed_test_field_string_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id'  => [
            'type' => 'serial',
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => ['test_field1' => ['test_field1']],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field1' => ['$type' => 'int']],
          ['test_field1' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_field1__key', 'unique' => TRUE, 'key' => ['test_field1' => 1]],
      ],
    ];

    $test_table5_changed_id_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'indexes' => ['test_field2' => ['test_field2']],
      ],
      'validation' => [
        '$and' => [
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          ['test_field2' => ['$type' => 'string']],
          ['test_field2' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_field2__idx', 'unique' => FALSE, 'key' => ['test_field2' => 1]],
      ],
    ];

    $test_table5_changed_test_field_string_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id'  => [
            'type' => 'serial',
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'indexes' => ['test_field2' => ['test_field2']],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field2' => ['$type' => 'string']],
          ['test_field2' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_field2__idx', 'unique' => FALSE, 'key' => ['test_field2' => 1]],
      ],
    ];

    $test_table5_changed_id_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
      ],
      'validation' => [
        '$and' => [
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          ['test_field3' => ['$type' => 'int']],
          ['test_field3' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field3' => 1]],
      ],
    ];

    $test_table5_changed_test_field_string_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id'  => [
            'type' => 'serial',
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field3' => ['$type' => 'int']],
          ['test_field3' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field3' => 1]],
      ],
    ];

    $test_table6_changed_id_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field1'],
        'indexes' => ['test_field_string' => ['test_field_string']],
      ],
      'validation' => [
        '$and' => [
          [
            '$or' => [
              ['test_field_string' => ['$type' => 'string']],
              ['test_field_string' => ['$exists' => FALSE]],
            ],
          ],
          ['test_field1' => ['$type' => 'int']],
          ['test_field1' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field1' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
      ],
    ];

    $test_table6_changed_test_field_string_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_field1' => ['test_field1']],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field1' => ['$type' => 'int']],
          ['test_field1' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field1__key', 'unique' => TRUE, 'key' => ['test_field1' => 1]],
      ],
    ];

    $test_table6_changed_id_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['test_field2'],
        'indexes' => [
          'test_field_string' => ['test_field_string'],
        ],
      ],
      'validation' => [
        '$and' => [
          [
            '$or' => [
              ['test_field_string' => ['$type' => 'string']],
              ['test_field_string' => ['$exists' => FALSE]],
            ],
          ],
          ['test_field2' => ['$type' => 'string']],
          ['test_field2' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field2' => 1]],
      ],
    ];

    $test_table6_changed_test_field_string_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
        'indexes' => ['test_field2' => ['test_field2']],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field2' => ['$type' => 'string']],
          ['test_field2' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field2__idx', 'unique' => FALSE, 'key' => ['test_field2' => 1]],
      ],
    ];

    $test_table6_changed_id_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'indexes' => ['test_field_string' => ['test_field_string']],
      ],
      'validation' => [
        '$and' => [
          [
            '$or' => [
              ['test_field_string' => ['$type' => 'string']],
              ['test_field_string' => ['$exists' => FALSE]],
            ],
          ],
          ['test_field3' => ['$type' => 'int']],
          ['test_field3' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field3' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
      ],
    ];

    $test_table6_changed_test_field_string_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field3' => ['$type' => 'int']],
          ['test_field3' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field3' => 1]],
      ],
    ];

    $change_embedded_base_4_embedded_5_changed_id_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => ['test_field1' => ['test_field1']],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                  ['test_table5.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                  ['test_table5.test_field1' => ['$type' => 'int']],
                  ['test_table5.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.test_field1__key', 'unique' => TRUE, 'key' => ['test_table5.test_field1' => 1]],
      ],
    ];

    $change_embedded_base_4_embedded_5_changed_test_field_string_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id'  => [
            'type' => 'serial',
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => ['test_field1' => ['test_field1']],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field1' => ['$type' => 'int']],
                  ['test_table5.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.test_field1__key', 'unique' => TRUE, 'key' => ['test_table5.test_field1' => 1]],
      ],
    ];

    $change_embedded_base_4_embedded_5_and_6_changed_id_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => ['test_field1' => ['test_field1']],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                  ['test_table5.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                  ['test_table5.test_field1' => ['$type' => 'int']],
                  ['test_table5.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.test_field1__key', 'unique' => TRUE, 'key' => ['test_table5.test_field1' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $change_embedded_base_4_embedded_5_and_6_changed_test_field_string_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id'  => [
            'type' => 'serial',
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'unique keys' => ['test_field1' => ['test_field1']],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field1' => ['$type' => 'int']],
                  ['test_table5.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.test_field1__key', 'unique' => TRUE, 'key' => ['test_table5.test_field1' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $change_embedded_base_4_embedded_6_and_5_changed_id_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field1'],
        'indexes' => ['test_field_string' => ['test_field_string']],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                  ['test_table6.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table6.test_field1' => ['$type' => 'int']],
                  ['test_table6.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.test_field1' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $change_embedded_base_4_embedded_6_and_5_changed_test_field_string_to_test_field1 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field1'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_field1' => ['test_field1']],
        'embedded_to_table' => 'test_table4',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  ['test_table6.test_field1' => ['$type' => 'int']],
                  ['test_table6.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field1__key', 'unique' => TRUE, 'key' => ['test_table6.test_field1' => 1]],
      ],
    ];

    $change_embedded_base_5_embedded_4_changed_id_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['test_field2'],
        'unique keys' => ['test_field' => ['test_field']],
        'embedded_to_table' => 'test_table5',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                  ['test_table4.test_field2' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                  ['test_table4.test_field2' => ['$type' => 'string']],
                  ['test_table4.test_field2' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.test_field2' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
      ],
    ];

    $change_embedded_base_5_embedded_4_changed_test_field_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
        'indexes' => ['test_field2' => ['test_field2']],
        'embedded_to_table' => 'test_table5',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field2' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field2' => ['$type' => 'string']],
                  ['test_table4.test_field2' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table4.test_field2__idx', 'unique' => FALSE, 'key' => ['test_table4.test_field2' => 1]],
      ],
    ];

    $change_embedded_base_5_embedded_4_and_6_changed_id_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['test_field2'],
        'unique keys' => ['test_field' => ['test_field']],
        'embedded_to_table' => 'test_table5',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                  ['test_table4.test_field2' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                  ['test_table4.test_field2' => ['$type' => 'string']],
                  ['test_table4.test_field2' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.test_field2' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $change_embedded_base_5_embedded_4_and_6_changed_test_field_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
        'indexes' => ['test_field2' => ['test_field2']],
        'embedded_to_table' => 'test_table5',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field2' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field2' => ['$type' => 'string']],
                  ['test_table4.test_field2' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table4.test_field2__idx', 'unique' => FALSE, 'key' => ['test_table4.test_field2' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $change_embedded_base_5_embedded_6_and_4_changed_id_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['test_field2'],
        'indexes' => [
          'test_field_string' => ['test_field_string'],
        ],
        'embedded_to_table' => 'test_table5',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.test_field_string' => ['$exists' => FALSE]],
                  ['test_table6.test_field2' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  [
                    '$or' => [
                      ['test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table6.test_field2' => ['$type' => 'string']],
                  ['test_table6.test_field2' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.test_field2' => 1]],
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $change_embedded_base_5_embedded_6_and_4_changed_test_field_string_to_test_field2 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
        'indexes' => ['test_field2' => ['test_field2']],
        'embedded_to_table' => 'test_table5',
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['id' => ['$gte' => 0]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table6.id' => ['$exists' => FALSE]],
                  ['test_table6.test_field2' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table6.id' => ['$type' => 'int']],
                  ['test_table6.id' => ['$exists' => TRUE]],
                  ['test_table6.test_field2' => ['$type' => 'string']],
                  ['test_table6.test_field2' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
        ['name' => 'test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table6.id' => 1]],
        ['name' => 'test_table6.test_field2__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field2' => 1]],
      ],
    ];

    $change_embedded_base_6_embedded_4_changed_id_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'unique keys' => ['test_field' => ['test_field']],
        'embedded_to_table' => 'test_table6',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                  ['test_table4.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                  ['test_table4.test_field3' => ['$type' => 'int']],
                  ['test_table4.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.test_field3' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
      ],
    ];

    $change_embedded_base_6_embedded_4_changed_test_field_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'embedded_to_table' => 'test_table6',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field3' => ['$type' => 'int']],
                  ['test_table4.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.test_field3' => 1]],
      ],
    ];

    $change_embedded_base_6_embedded_4_and_5_changed_id_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'unique keys' => ['test_field' => ['test_field']],
        'embedded_to_table' => 'test_table6',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                  ['test_table4.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                  ['test_table4.test_field3' => ['$type' => 'int']],
                  ['test_table4.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.test_field3' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
      ],
    ];

    $change_embedded_base_6_embedded_4_and_5_changed_test_field_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'embedded_to_table' => 'test_table6',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field3' => ['$type' => 'int']],
                  ['test_table4.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.test_field3' => 1]],
      ],
    ];

    $change_embedded_base_6_embedded_5_and_4_changed_id_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'test_field_string'  => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'embedded_to_table' => 'test_table6',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.test_field_string' => ['$exists' => FALSE]],
                  ['test_table5.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table5.test_field_string' => ['$exists' => TRUE]],
                  ['test_table5.test_field3' => ['$type' => 'int']],
                  ['test_table5.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
        ['name' => 'test_table5.__pkey', 'unique' => TRUE, 'key' => ['test_table5.test_field3' => 1]],
      ],
    ];

    $change_embedded_base_6_embedded_5_and_4_changed_test_field_string_to_test_field3 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id'  => [
            'type' => 'serial',
          ],
          'test_field3' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'embedded_to_table' => 'test_table6',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table4.id' => ['$exists' => FALSE]],
                  ['test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table4.id' => ['$type' => 'int']],
                  ['test_table4.id' => ['$exists' => TRUE]],
                  ['test_table4.test_field' => ['$type' => 'int']],
                  ['test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table5.id' => ['$exists' => FALSE]],
                  ['test_table5.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table5.id' => ['$type' => 'int']],
                  ['test_table5.id' => ['$exists' => TRUE]],
                  ['test_table5.id' => ['$gte' => 0]],
                  ['test_table5.test_field3' => ['$type' => 'int']],
                  ['test_table5.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
        ['name' => 'test_table5.__pkey', 'unique' => TRUE, 'key' => ['test_table5.test_field3' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_3_on_table_1 = [
      'schema' => [
        'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
        'fields' => [
          'id'  => [
            'type' => 'int',
            'default' => NULL,
            'not null' => TRUE,
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
          'test_field3'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
      ],
      'validation' => [
        '$and' => [
          ['id' => ['$type' => 'int']],
          ['id' => ['$exists' => TRUE]],
          ['test_field_string' => ['$type' => 'string']],
          ['test_field_string' => ['$exists' => TRUE]],
          [
            '$or' => [
              ['test_field_string_ascii' => ['$type' => 'string']],
              ['test_field_string_ascii' => ['$exists' => FALSE]],
            ],
          ],
          ['test_field3' => ['$type' => 'int']],
          ['test_field3' => ['$exists' => TRUE]],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.id' => ['$type' => 'int']],
                  ['test_table2.test_table4.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field3' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_1_on_table_4 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field1' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field1'],
        'unique keys' => [
          'test_field' => ['test_field'],
        ],
        'embedded_to_table' => 'test_table2',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field1' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field1' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field1' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field1' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_2_on_table_4 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field2' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['test_field2'],
        'unique keys' => ['test_field' => ['test_field']],
        'embedded_to_table' => 'test_table2',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field2' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field2' => ['$type' => 'string']],
                  ['test_table2.test_table4.test_field2' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field2' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
      ],
    ];

    $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_3_on_table_4 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field3'  => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['test_field3'],
        'unique keys' => ['test_field' => ['test_field']],
        'embedded_to_table' => 'test_table2',
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
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.id' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.id' => ['$type' => 'int']],
                  ['test_table2.id' => ['$exists' => TRUE]],
                  ['test_table2.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_null' => ['$type' => 'int']],
                      ['test_table2.test_field_int_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_int_not_null' => ['$type' => 'int']],
                  ['test_table2.test_field_int_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_int_default' => ['$type' => 'int']],
                      ['test_table2.test_field_int_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_null' => ['$type' => 'double']],
                      ['test_table2.test_field_float_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_float_not_null' => ['$type' => 'double']],
                  ['test_table2.test_field_float_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_float_default' => ['$type' => 'double']],
                      ['test_table2.test_field_float_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_null' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_field_numeric_not_null' => ['$type' => 'decimal']],
                  ['test_table2.test_field_numeric_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_field_numeric_default' => ['$type' => 'decimal']],
                      ['test_table2.test_field_numeric_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.id' => ['$gte' => 0]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_varchar_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_varchar_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_varchar_default' => ['$exists' => FALSE]],
                    ],
                  ],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_null' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_null' => ['$exists' => FALSE]],
                    ],
                  ],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_field_text_not_null' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_field_text_default' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_field_text_default' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table5.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table5.id' => ['$exists' => TRUE]],
                  ['test_table2.test_table3.test_table5.id' => ['$gte' => 0]],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$type' => 'string']],
                  ['test_table2.test_table3.test_table5.test_field_string' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => FALSE]],
                  ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table3.test_table6.id' => ['$type' => 'int']],
                  ['test_table2.test_table3.test_table6.id' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$type' => 'string']],
                      ['test_table2.test_table3.test_table6.test_field_string' => ['$exists' => FALSE]],
                    ],
                  ],
                ],
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$and' => [
                  ['test_table2.test_table4.test_field' => ['$exists' => FALSE]],
                  ['test_table2.test_table4.test_field3' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table2.test_table4.test_field' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field' => ['$exists' => TRUE]],
                  ['test_table2.test_table4.test_field3' => ['$type' => 'int']],
                  ['test_table2.test_table4.test_field3' => ['$exists' => TRUE]],
                ],
              ],
            ],
          ],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field3' => 1]],
      ],
    ];

    return [
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'id',
        static::TEST_FIELD1,
        $test_table4_changed_id_to_test_field1,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'test_field',
        static::TEST_FIELD1,
        $test_table4_changed_test_field_to_test_field1,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'id',
        static::TEST_FIELD2,
        $test_table4_changed_id_to_test_field2,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'test_field',
        static::TEST_FIELD2,
        $test_table4_changed_test_field_to_test_field2,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'id',
        static::TEST_FIELD3,
        $test_table4_changed_id_to_test_field3,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'test_field',
        static::TEST_FIELD3,
        $test_table4_changed_test_field_to_test_field3,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'id',
        static::TEST_FIELD1,
        $test_table5_changed_id_to_test_field1,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'test_field_string',
        static::TEST_FIELD1,
        $test_table5_changed_test_field_string_to_test_field1,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'id',
        static::TEST_FIELD2,
        $test_table5_changed_id_to_test_field2,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'test_field_string',
        static::TEST_FIELD2,
        $test_table5_changed_test_field_string_to_test_field2,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'id',
        static::TEST_FIELD3,
        $test_table5_changed_id_to_test_field3,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'test_field_string',
        static::TEST_FIELD3,
        $test_table5_changed_test_field_string_to_test_field3,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        'id',
        static::TEST_FIELD1,
        $test_table6_changed_id_to_test_field1,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        'test_field_string',
        static::TEST_FIELD1,
        $test_table6_changed_test_field_string_to_test_field1,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        'id',
        static::TEST_FIELD2,
        $test_table6_changed_id_to_test_field2,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        'test_field_string',
        static::TEST_FIELD2,
        $test_table6_changed_test_field_string_to_test_field2,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        'id',
        static::TEST_FIELD3,
        $test_table6_changed_id_to_test_field3,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        'test_field_string',
        static::TEST_FIELD3,
        $test_table6_changed_test_field_string_to_test_field3,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        'id',
        static::TEST_FIELD1,
        $change_embedded_base_4_embedded_5_changed_id_to_test_field1,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        'test_field_string',
        static::TEST_FIELD1,
        $change_embedded_base_4_embedded_5_changed_test_field_string_to_test_field1,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE5['name'],
        'id',
        static::TEST_FIELD1,
        $change_embedded_base_4_embedded_5_and_6_changed_id_to_test_field1,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE5['name'],
        'test_field_string',
        static::TEST_FIELD1,
        $change_embedded_base_4_embedded_5_and_6_changed_test_field_string_to_test_field1,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE6, static::TEST_TABLE5]],
        static::TEST_TABLE6['name'],
        'id',
        static::TEST_FIELD1,
        $change_embedded_base_4_embedded_6_and_5_changed_id_to_test_field1,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE6, static::TEST_TABLE5]],
        static::TEST_TABLE6['name'],
        'test_field_string',
        static::TEST_FIELD1,
        $change_embedded_base_4_embedded_6_and_5_changed_test_field_string_to_test_field1,
      ],
      [
        static::TEST_TABLE5,
        [static::TEST_TABLE5['name'] => [static::TEST_TABLE4]],
        static::TEST_TABLE4['name'],
        'id',
        static::TEST_FIELD2,
        $change_embedded_base_5_embedded_4_changed_id_to_test_field2,
      ],
      [
        static::TEST_TABLE5,
        [static::TEST_TABLE5['name'] => [static::TEST_TABLE4]],
        static::TEST_TABLE4['name'],
        'test_field',
        static::TEST_FIELD2,
        $change_embedded_base_5_embedded_4_changed_test_field_to_test_field2,
      ],
      [
        static::TEST_TABLE5,
        [static::TEST_TABLE5['name'] => [static::TEST_TABLE4, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        'id',
        static::TEST_FIELD2,
        $change_embedded_base_5_embedded_4_and_6_changed_id_to_test_field2,
      ],
      [
        static::TEST_TABLE5,
        [static::TEST_TABLE5['name'] => [static::TEST_TABLE4, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        'test_field',
        static::TEST_FIELD2,
        $change_embedded_base_5_embedded_4_and_6_changed_test_field_to_test_field2,
      ],
      [
        static::TEST_TABLE5,
        [static::TEST_TABLE5['name'] => [static::TEST_TABLE6, static::TEST_TABLE4]],
        static::TEST_TABLE6['name'],
        'id',
        static::TEST_FIELD2,
        $change_embedded_base_5_embedded_6_and_4_changed_id_to_test_field2,
      ],
      [
        static::TEST_TABLE5,
        [static::TEST_TABLE5['name'] => [static::TEST_TABLE6, static::TEST_TABLE4]],
        static::TEST_TABLE6['name'],
        'test_field_string',
        static::TEST_FIELD2,
        $change_embedded_base_5_embedded_6_and_4_changed_test_field_string_to_test_field2,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4]],
        static::TEST_TABLE4['name'],
        'id',
        static::TEST_FIELD3,
        $change_embedded_base_6_embedded_4_changed_id_to_test_field3,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4]],
        static::TEST_TABLE4['name'],
        'test_field',
        static::TEST_FIELD3,
        $change_embedded_base_6_embedded_4_changed_test_field_to_test_field3,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4, static::TEST_TABLE5]],
        static::TEST_TABLE4['name'],
        'id',
        static::TEST_FIELD3,
        $change_embedded_base_6_embedded_4_and_5_changed_id_to_test_field3,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4, static::TEST_TABLE5]],
        static::TEST_TABLE4['name'],
        'test_field',
        static::TEST_FIELD3,
        $change_embedded_base_6_embedded_4_and_5_changed_test_field_to_test_field3,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE5, static::TEST_TABLE4]],
        static::TEST_TABLE5['name'],
        'id',
        static::TEST_FIELD3,
        $change_embedded_base_6_embedded_5_and_4_changed_id_to_test_field3,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE5, static::TEST_TABLE4]],
        static::TEST_TABLE5['name'],
        'test_field_string',
        static::TEST_FIELD3,
        $change_embedded_base_6_embedded_5_and_4_changed_test_field_string_to_test_field3,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE1['name'],
        'test_field',
        static::TEST_FIELD3,
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_3_on_table_1,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        'id',
        static::TEST_FIELD1,
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_1_on_table_4,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        'id',
        static::TEST_FIELD3,
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_3_on_table_4,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        'id',
        static::TEST_FIELD2,
        $base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_2_on_table_4,
      ],
    ];
  }

  /**
   * @covers ::changeField
   * @covers ::fieldExists
   * @dataProvider providerChangeField
   */
  public function testChangeField($base_table_data, $embedded_tables_data, $table_name_to_change_field, $field_name_old, $field_data, $base_table_with_embedded_tables_and_changed_field) {
    $this->assertFalse($this->schema->tableExists($base_table_data['name']), 'The table does not exist in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertFalse($this->schema->tableExists($embedded_table_data['name']), 'The embedded table does not exist in the MongoDB database.');
      }
    }
    $this->assertFalse($this->schema->fieldExists($table_name_to_change_field, $field_name_old), 'The field does not exist on the embedded table in the MongoDB database.');
    if ($field_name_old != $field_data['name']) {
      $this->assertFalse($this->schema->fieldExists($table_name_to_change_field, $field_data['name']), 'The field does not exist on the embedded table in the MongoDB database.');
    }

    // Create the table and add the field.
    $this->schema->createTable($base_table_data['name'], $base_table_data['schema']);
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->schema->createEmbeddedTable($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
      }
    }

    $this->assertTrue($this->schema->tableExists($base_table_data['name']), 'The table exists in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertTrue($this->schema->tableExists($embedded_table_data['name']), 'The embedded table exists in the MongoDB database.');
      }
    }
    $this->assertTrue($this->schema->fieldExists($table_name_to_change_field, $field_name_old), 'The field exists on the embedded table in the MongoDB database.');
    $this->assertFalse($this->schema->fieldExists($table_name_to_change_field, $field_data['name']), 'The field does not exist on the embedded table in the MongoDB database.');

    // Call the to be tested method: Schema::changeField().
    $this->schema->changeField($table_name_to_change_field, $field_name_old, $field_data['name'], $field_data['spec'], $field_data['keys']);

    $this->assertTrue($this->schema->tableExists($base_table_data['name']), 'The table exists in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertTrue($this->schema->tableExists($embedded_table_data['name']), 'The embedded table exists in the MongoDB database.');
      }
    }
    if ($field_name_old != $field_data['name']) {
      $this->assertFalse($this->schema->fieldExists($table_name_to_change_field, $field_name_old), 'The field does not exist on the embedded table in the MongoDB database.');
    }
    $this->assertTrue($this->schema->fieldExists($table_name_to_change_field, $field_data['name']), 'The field exists on the embedded table in the MongoDB database.');

    // Check that the validation, schema or index data are what they should be.
    $this->checkTableValidation($base_table_data['name'], $base_table_with_embedded_tables_and_changed_field['validation']);
    if ($base_table_data['name'] == $table_name_to_change_field) {
      $this->checkTableSchema($base_table_data['name'], $base_table_with_embedded_tables_and_changed_field['schema']);
    }
    else {
      $this->checkTableSchema($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_change_field) {
          $this->checkTableSchema($table_name_to_change_field, $base_table_with_embedded_tables_and_changed_field['schema']);
        }
        else {
          $embedded_table_data['schema']['embedded_to_table'] = $parent_table_name;
          $this->checkTableSchema($embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    if ($base_table_data['name'] == $table_name_to_change_field) {
      $this->checkTableIndexes($base_table_data['name'], $base_table_with_embedded_tables_and_changed_field['schema']);
    }
    else {
      $this->checkTableIndexes($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_change_field) {
          $this->checkEmbeddedTableIndexes($parent_table_name, $table_name_to_change_field, $base_table_with_embedded_tables_and_changed_field['schema']);
        }
        else {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    $this->checkExpectedIndexesAgainstDatabase($base_table_data['name'], $base_table_with_embedded_tables_and_changed_field['indexes']);
    // Update the embedded_tables_data with adding field to a specific embedded
    // table.
    if ($base_table_data['name'] == $table_name_to_change_field) {
      $base_table_data['schema'] = $base_table_with_embedded_tables_and_changed_field['schema'];
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $key => $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_change_field) {
          $embedded_tables_data[$parent_table_name][$key]['schema'] = $base_table_with_embedded_tables_and_changed_field['schema'];
        }
      }
    }
    $this->checkTableNumberOfIndexes($base_table_data, $embedded_tables_data);
  }

  /**
   * @covers ::changeField
   */
  public function testChangeFieldForFieldDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    // Create the to be renamed table.
    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');

    // If we try to change a non existent field from a table an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->changeField(static::TEST_TABLE4['name'], 'does_not_exist_field', static::TEST_FIELD1['name'], static::TEST_FIELD1['spec'], static::TEST_FIELD1['keys']);
  }

  /**
   * @covers ::changeField
   */
  public function testChangeFieldForNewFieldExists() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    // Create the to be renamed table.
    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');

    // If we try to change a field from a table to an existing field an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->changeField(static::TEST_TABLE4['name'], 'id', 'test_field', static::TEST_FIELD1['spec'], static::TEST_FIELD1['keys']);
  }

  /**
   * @covers ::changeField
   */
  public function testChangeFieldForBaseTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    // If we try to rename an embedded table on a non existent base table an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->changeField(static::TEST_TABLE4['name'], 'id', static::TEST_FIELD1['name'], static::TEST_FIELD1['spec'], static::TEST_FIELD1['keys']);
  }

  /**
   * @covers ::changeField
   */
  public function testChangedFieldForEmbeddedFieldDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The embedded table does not exist in the MongoDB database.');

    // Create the to be renamed table.
    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE4['name'], static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The embedded table exists in the MongoDB database.');

    // If we try to change a non existent field from a table an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->changeField(static::TEST_TABLE5['name'], 'does_not_exist_field', static::TEST_FIELD1['name'], static::TEST_FIELD1['spec'], static::TEST_FIELD1['keys']);
  }

  /**
   * @covers ::changeField
   */
  public function testChangeFieldForNewEmbeddedFieldExists() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The embedded table does not exist in the MongoDB database.');

    // Create the to be renamed table.
    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE4['name'], static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The embedded table exists in the MongoDB database.');

    // If we try to change a field from a table to an existing field an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->changeField(static::TEST_TABLE5['name'], 'id', 'test_field_string', static::TEST_FIELD1['spec'], static::TEST_FIELD1['keys']);
  }

}
