<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel;

// phpcs:ignoreFile

/**
 * Tests MongoDB other method via the schema API.
 *
 * @group MongoDB
 * @coversDefaultClass \Drupal\mongodb\Driver\Database\mongodb\Schema
 */
class SchemaNotTableOrFieldTest extends SchemaTestBase {

  /**
   * Data provider for testAddPrimaryKey().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the array of table data to use as embedded tables (the name, the
   *       schema and the validation).
   *     - the array of field names to used for creating the primary key.
   *     - the expected schema for the table after adding the primary key.
   */
  public static function providerAddPrimaryKey() {
    $add_primary_key_table_5_with_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
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
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
      ],
    ];

    $add_primary_key_table_5_with_id_and_test_field_string = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id', 'test_field_string'],
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
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1, 'test_field_string' => 1]],
      ],
    ];

    $add_primary_key_table_5_with_test_field_string_and_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['test_field_string', 'id'],
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
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['test_field_string' => 1, 'id' => 1]],
      ],
    ];

    $add_primary_key_base_4_with_embedded_5_and_field_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.__pkey', 'unique' => TRUE, 'key' => ['test_table5.id' => 1]],
      ],
    ];

    $add_primary_key_base_4_with_embedded_5_and_field_id_and_test_field_string = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id', 'test_field_string'],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.__pkey', 'unique' => TRUE, 'key' => ['test_table5.id' => 1, 'test_table5.test_field_string' => 1]],
      ],
    ];

    $add_primary_key_base_4_with_embedded_5_and_field_test_field_string_and_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['test_field_string', 'id'],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.__pkey', 'unique' => TRUE, 'key' => ['test_table5.test_field_string' => 1, 'test_table5.id' => 1]],
      ],
    ];

    $add_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_id_on_table_3 = [
      'schema' => [
        'description' => 'Schema table for testing varchar with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_varchar_null' => [
            'type' => 'varchar',
          ],
          'test_field_varchar_not_null' => [
            'type' => 'varchar',
            'not null' => TRUE,
          ],
          'test_field_varchar_default' => [
            'type' => 'varchar',
            'default' => 4,
          ],
          'test_field_text_null' => [
            'type' => 'text',
          ],
          'test_field_text_not_null' => [
            'type' => 'text',
            'not null' => TRUE,
          ],
          'test_field_text_default' => [
            'type' => 'text',
            'default' => 4,
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
        ['name' => 'test_table2.test_table3.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.id' => 1]],
      ],
    ];

    $add_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_id_on_table_5 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'primary key' => ['id'],
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
        ['name' => 'test_table2.test_table3.test_table5.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table5.id' => 1]],
      ],
    ];

    return [
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        ['id'],
        $add_primary_key_table_5_with_id,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        ['id', 'test_field_string'],
        $add_primary_key_table_5_with_id_and_test_field_string,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        ['test_field_string', 'id'],
        $add_primary_key_table_5_with_test_field_string_and_id,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        ['id'],
        $add_primary_key_base_4_with_embedded_5_and_field_id,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        ['id', 'test_field_string'],
        $add_primary_key_base_4_with_embedded_5_and_field_id_and_test_field_string,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        ['test_field_string', 'id'],
        $add_primary_key_base_4_with_embedded_5_and_field_test_field_string_and_id,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE3['name'],
        ['id'],
        $add_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_id_on_table_3,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE5['name'],
        ['id'],
        $add_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_with_field_id_on_table_5,
      ],
    ];
  }

  /**
   * @covers ::addPrimaryKey
   * @covers ::constraintExists
   * @covers ::primaryKeyExists
   * @dataProvider providerAddPrimaryKey
   */
  public function testAddPrimaryKey($base_table_data, $embedded_tables_data, $table_name_to_add_primary_key, $fields_data, $added_primary_key_table_data) {
    $this->assertFalse($this->schema->tableExists($base_table_data['name']), 'The table does not exist in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertFalse($this->schema->tableExists($embedded_table_data['name']), 'The embedded table does not exist in the MongoDB database.');
      }
    }
    $this->assertFalse($this->schema->primaryKeyExists($table_name_to_add_primary_key), 'The table does not have a primary key.');

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

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table_name_to_add_primary_key);
    $this->assertFalse($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . '__pkey'), 'The embedded table does not have a primary key.');
    $this->assertFalse($this->schema->primaryKeyExists($table_name_to_add_primary_key), 'The table does not have a primary key.');

    // Call the to be tested method: Schema::addPrimaryKey().
    $this->schema->addPrimaryKey($table_name_to_add_primary_key, $fields_data);

    $this->assertTrue($this->schema->tableExists($base_table_data['name']), 'The table exists in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertTrue($this->schema->tableExists($embedded_table_data['name']), 'The embedded table exists in the MongoDB database.');
      }
    }
    $this->assertTrue($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . '__pkey'), 'The table has a primary key.');
    $this->assertTrue($this->schema->primaryKeyExists($table_name_to_add_primary_key), 'The table has a primary key.');

    // Check that the validation, schema or index data is what is it should be
    // for all table including the embedded table with the added primary key.
    $this->checkTableValidation($base_table_data['name'], $added_primary_key_table_data['validation']);
    if ($base_table_data['name'] == $table_name_to_add_primary_key) {
      $this->checkTableSchema($base_table_data['name'], $added_primary_key_table_data['schema']);
    }
    else {
      $this->checkTableSchema($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_add_primary_key) {
          $this->checkTableSchema($embedded_table_data['name'], $added_primary_key_table_data['schema']);
        }
        else {
          $embedded_table_data['schema']['embedded_to_table'] = $parent_table_name;
          $this->checkTableSchema($embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    if ($base_table_data['name'] == $table_name_to_add_primary_key) {
      $this->checkTableIndexes($base_table_data['name'], $added_primary_key_table_data['schema']);
    }
    else {
      $this->checkTableIndexes($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_add_primary_key) {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $added_primary_key_table_data['schema']);
        }
        else {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    $this->checkExpectedIndexesAgainstDatabase($base_table_data['name'], $added_primary_key_table_data['indexes']);

    // Update the embedded_tables_data with adding a primary key to an embedded
    // table.
    if ($base_table_data['name'] == $table_name_to_add_primary_key) {
      $base_table_data['schema'] = $added_primary_key_table_data['schema'];
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $key => $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_add_primary_key) {
          $embedded_tables_data[$parent_table_name][$key]['schema'] = $added_primary_key_table_data['schema'];
        }
      }
    }
    $this->checkTableNumberOfIndexes($base_table_data, $embedded_tables_data);
  }

  /**
   * @covers ::addPrimaryKey
   */
  public function testAddPrimaryKeyForTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');

    // If we try to add a primary key to a table that does not exist an
    // exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->addPrimaryKey(static::TEST_TABLE5['name'], ['id']);
  }

  /**
   * @covers ::addPrimaryKey
   * @covers ::primaryKeyExists
   */
  public function testAddPrimaryKeyForPrimaryKeyExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE4['name'], 'pkey'), 'The table has a primary key.');
    $this->assertTrue($this->schema->primaryKeyExists(static::TEST_TABLE4['name']), 'The table has a primary key.');

    // If we try to add a primary key to a table that has a primary key an
    // exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addPrimaryKey(static::TEST_TABLE4['name'], ['id']);
  }

  /**
   * @covers ::addPrimaryKey
   * @covers ::primaryKeyExists
   * @covers ::uniqueKeyExists
   */
  public function testAddPrimaryKeyForUniqueKeyExistsOnTheSameFields() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');

    $this->schema->dropPrimaryKey(static::TEST_TABLE4['name']);

    $this->assertFalse($this->schema->constraintExists(static::TEST_TABLE4['name'], 'pkey'), 'The table does not have a primary key.');
    $this->assertFalse($this->schema->primaryKeyExists(static::TEST_TABLE4['name']), 'The table does not have a primary key.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE4['name'], 'test_field__key'), 'The table has the unique key.');
    $this->assertTrue($this->schema->uniqueKeyExists(static::TEST_TABLE4['name'], 'test_field'), 'The table has the unique key.');

    // If we try to add an unique key to a table that has an unique index on the
    // same fields an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addPrimaryKey(static::TEST_TABLE4['name'], ['test_field']);
  }

  /**
   * @covers ::addPrimaryKey
   */
  public function testAddPrimaryKeyForEmbeddedPrimaryKeyExists() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE6['name']), 'The embedded table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE4['name'], static::TEST_TABLE6['name'], static::TEST_TABLE6['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE6['name']), 'The embedded table exists in the MongoDB database.');

    // If we try to add a primary key to a table that has a primary key an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addPrimaryKey(static::TEST_TABLE6['name'], ['id']);
  }

  /**
   * @covers ::addPrimaryKey
   * @covers ::primaryKeyExists
   * @covers ::uniqueKeyExists
   */
  public function testAddPrimaryKeyForEmbeddedUniqueKeyExistsOnTheSameFields() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE5['name'], static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table exists in the MongoDB database.');

    $this->schema->dropPrimaryKey(static::TEST_TABLE4['name']);

    $this->assertFalse($this->schema->constraintExists(static::TEST_TABLE5['name'], static::TEST_TABLE4['name'] . '.__pkey'), 'The embedded table does not have a primary key.');
    $this->assertFalse($this->schema->primaryKeyExists(static::TEST_TABLE4['name']), 'The table does not have a primary key.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE5['name'], static::TEST_TABLE4['name'] . '.test_field__key'), 'The embedded table has the unique key.');
    $this->assertTrue($this->schema->uniqueKeyExists(static::TEST_TABLE4['name'], 'test_field'), 'The table has the unique key.');

    // If we try to add an unique key to an embedded table that has an unique
    // index on the same fields an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addPrimaryKey(static::TEST_TABLE4['name'], ['test_field']);
  }

  /**
   * Data provider for testDropPrimaryKey().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the expected schema for the embedded table after dropping the primary
   *       key and the validation for the base table after dropping the primary
   *       key.
   */
  public static function providerDropPrimaryKey() {
    $drop_primary_key_table_1_data = [
      'schema' => [
        'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'default' => NULL,
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
            'description' => 'Schema column description for string.',
          ],
          'test_field_string_ascii' => [
            'type' => 'varchar_ascii',
            'length' => 255,
            'description' => 'Schema column description for ASCII string.',
          ],
        ],
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
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
      ],
    ];

    $drop_primary_key_table_2_data = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
            'type' => 'numeric',
            'default' => 4,
          ],
        ],
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
        ['name' => 'test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_field_int_not_null' => 1, 'test_field_float_not_null' => 1, 'test_field_numeric_not_null' => 1]],
        ['name' => 'test_fields_null__key', 'unique' => TRUE, 'key' => ['test_field_int_null' => 1, 'test_field_float_null' => 1, 'test_field_numeric_null' => 1]],
        ['name' => 'test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_field_int_default' => 1, 'test_field_float_default' => 1, 'test_field_numeric_default' => 1]],
        ['name' => 'test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_field_int_null' => 1]],
      ],
    ];

    $drop_primary_key_table_4_data = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
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
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
      ],
    ];

    $drop_primary_key_table_6_data = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_string' => [
            'type' => 'varchar',
          ],
        ],
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
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
      ],
    ];

    $drop_primary_key_base_4_with_embedded_6_data = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_string' => [
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
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $drop_primary_key_base_4_with_embedded_6_and_5_data = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_string' => [
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
        ['name' => 'test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table6.test_field_string' => 1]],
      ],
    ];

    $drop_primary_key_base_6_with_embedded_4_data = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
      ],
    ];

    $drop_primary_key_base_6_with_embedded_4_and_5_data = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
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

    $drop_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_1 = [
      'schema' => [
        'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'default' => NULL,
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
            'description' => 'Schema column description for string.',
          ],
          'test_field_string_ascii' => [
            'type' => 'varchar_ascii',
            'length' => 255,
            'description' => 'Schema column description for ASCII string.',
          ],
        ],
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
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
      ],
    ];

    $drop_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_2 = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
            'type' => 'numeric',
            'default' => 4,
          ],
        ],
        'unique keys' => [
          'test_fields_not_null' => ['test_field_int_not_null', 'test_field_float_not_null', 'test_field_numeric_not_null'],
          'test_fields_null' => ['test_field_int_null', 'test_field_float_null', 'test_field_numeric_null'],
        ],
        'indexes' => [
          'test_fields_default' => ['test_field_int_default', 'test_field_float_default', 'test_field_numeric_default'],
          'test_field_int_null' => ['test_field_int_null'],
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
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
      ],
    ];

    $drop_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_4 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
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
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
      ],
    ];

    $drop_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_6 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_string' => [
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
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
      ],
    ];

    return [
      [
        static::TEST_TABLE1,
        [],
        static::TEST_TABLE1['name'],
        $drop_primary_key_table_1_data,
      ],
      [
        static::TEST_TABLE2,
        [],
        static::TEST_TABLE2['name'],
        $drop_primary_key_table_2_data,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        $drop_primary_key_table_4_data,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        $drop_primary_key_table_6_data,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE6]],
        static::TEST_TABLE6['name'],
        $drop_primary_key_base_4_with_embedded_6_data,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE6, static::TEST_TABLE5]],
        static::TEST_TABLE6['name'],
        $drop_primary_key_base_4_with_embedded_6_and_5_data,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4]],
        static::TEST_TABLE4['name'],
        $drop_primary_key_base_6_with_embedded_4_data,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4, static::TEST_TABLE5]],
        static::TEST_TABLE4['name'],
        $drop_primary_key_base_6_with_embedded_4_and_5_data,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE1['name'],
        $drop_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_1,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE2['name'],
        $drop_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_2,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        $drop_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_4,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE6['name'],
        $drop_primary_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_6,
      ],
    ];
  }

  /**
   * @covers ::dropPrimaryKey
   * @covers ::constraintExists
   * @covers ::primaryKeyExists
   * @dataProvider providerDropPrimaryKey
   */
  public function testDropPrimaryKey($base_table_data, $embedded_tables_data, $table_name_to_drop_primary_key, $dropped_primary_key_table_data) {
    $this->assertFalse($this->schema->tableExists($base_table_data['name']), 'The table does not exist in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertFalse($this->schema->tableExists($embedded_table_data['name']), 'The embedded table does not exist in the MongoDB database.');
      }
    }
    $this->assertFalse($this->schema->primaryKeyExists($table_name_to_drop_primary_key), 'The table does not have a primary key.');

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

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table_name_to_drop_primary_key);
    $this->assertTrue($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . '__pkey'), 'The embedded table has a primary key.');
    $this->assertTrue($this->schema->primaryKeyExists($table_name_to_drop_primary_key), 'The table has a primary key.');

    // Call the to be tested method: Schema::dropPrimaryKey().
    $this->schema->dropPrimaryKey($table_name_to_drop_primary_key);

    $this->assertTrue($this->schema->tableExists($base_table_data['name']), 'The table exists in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertTrue($this->schema->tableExists($embedded_table_data['name']), 'The embedded table exists in the MongoDB database.');
      }
    }
    $this->assertFalse($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . '__pkey'), 'The embedded table does not have a primary key.');
    $this->assertFalse($this->schema->primaryKeyExists($table_name_to_drop_primary_key), 'The table does not have a primary key.');

    $this->checkExpectedIndexesAgainstDatabase($base_table_data['name'], $dropped_primary_key_table_data['indexes']);

    // Check that the validation, schema or index data is what is it should be
    // for all table including the embedded table without the dropped primary key.
    $this->checkTableValidation($base_table_data['name'], $dropped_primary_key_table_data['validation']);
    if ($base_table_data['name'] == $table_name_to_drop_primary_key) {
      $this->checkTableSchema($base_table_data['name'], $dropped_primary_key_table_data['schema']);
    }
    else {
      $this->checkTableSchema($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_primary_key) {
          $this->checkTableSchema($embedded_table_data['name'], $dropped_primary_key_table_data['schema']);
        }
        else {
          $embedded_table_data['schema']['embedded_to_table'] = $parent_table_name;
          $this->checkTableSchema($embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    if ($base_table_data['name'] == $table_name_to_drop_primary_key) {
      $this->checkTableIndexes($base_table_data['name'], $dropped_primary_key_table_data['schema']);
    }
    else {
      $this->checkTableIndexes($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_primary_key) {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $dropped_primary_key_table_data['schema']);
        }
        else {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }

    // Update the embedded_tables_data with adding a primary key to an embedded
    // table.
    if ($base_table_data['name'] == $table_name_to_drop_primary_key) {
      $base_table_data['schema'] = $dropped_primary_key_table_data['schema'];
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $key => $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_primary_key) {
          $embedded_tables_data[$parent_table_name][$key]['schema'] = $dropped_primary_key_table_data['schema'];
        }
      }
    }
    $this->checkTableNumberOfIndexes($base_table_data, $embedded_tables_data);
  }

  /**
   * @covers ::dropPrimaryKey
   * @covers ::primaryKeyExists
   */
  public function testDropPrimaryKeyForPrimaryKeyDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table exists in the MongoDB database.');
    $this->assertFalse($this->schema->constraintExists(static::TEST_TABLE5['name'], 'pkey'), 'The table does not have a primary key.');
    $this->assertFalse($this->schema->primaryKeyExists(static::TEST_TABLE5['name']), 'The table does not have a primary key.');

    // If we try to drop a non existent primary key on a table an exception
    // should be thrown.
    $this->assertFalse($this->schema->dropPrimaryKey(static::TEST_TABLE5['name']), 'The table does not have a primary key.');
  }

  /**
   * @covers ::dropPrimaryKey
   * @covers ::primaryKeyExists
   */
  public function testDropPrimaryKeyForEmbeddedPrimaryKeyDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The embedded table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE4['name'], static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The embedded table exists in the MongoDB database.');
    $this->assertFalse($this->schema->constraintExists(static::TEST_TABLE4['name'], static::TEST_TABLE5['name'] . '.__pkey'), 'The embedded table does not have the primary key.');
    $this->assertFalse($this->schema->primaryKeyExists(static::TEST_TABLE5['name']), 'The embedded table does not have a primary key.');

    // If we try to drop a non existent primary key from a table an exception
    // should be thrown.
    $this->assertFalse($this->schema->dropPrimaryKey(static::TEST_TABLE5['name']), 'The embedded table does not have a primary key.');
  }

  /**
   * Data provider for testAddUniqueKey().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the array of table data to use as embedded tables (the name, the
   *       schema and the validation).
   *     - the name to used for creating the unique key.
   *     - the array of field names to used for creating the unique key.
   *     - the expected schema for the table after adding the unique key.
   */
  public static function providerAddUniqueKey() {
    $add_unique_key_table_5_with_unique_id_key = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'unique keys' => ['unique_id_key' => ['id']],
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
        ['name' => 'unique_id_key__key', 'unique' => TRUE, 'key' => ['id' => 1]],
      ],
    ];

    $add_unique_key_table_5_with_unique_id_and_test_field_string_key = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'unique keys' => ['unique_id_and_test_field_string_key' => ['id', 'test_field_string']],
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
        ['name' => 'unique_id_and_test_field_string_key__key', 'unique' => TRUE, 'key' => ['id' => 1, 'test_field_string' => 1]],
      ],
    ];

    $add_unique_key_table_5_with_unique_test_field_string_and_id_key = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'unique keys' => ['unique_test_field_string_and_id_key' => ['test_field_string', 'id']],
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
        ['name' => 'unique_test_field_string_and_id_key__key', 'unique' => TRUE, 'key' => ['test_field_string' => 1, 'id' => 1]],
      ],
    ];

    $add_unique_key_table_4_with_unique_id_and_test_field_key = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_field' => ['test_field'],
          'unique_id_and_test_field_key' => ['id', 'test_field'],
        ],
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
        ['name' => 'unique_id_and_test_field_key__key', 'unique' => TRUE, 'key' => ['id' => 1, 'test_field' => 1]],
      ],
    ];

    $add_unique_key_table_4_with_unique_test_field_and_id_key = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_field' => ['test_field'],
          'unique_test_field_and_id_key' => ['test_field', 'id'],
        ],
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
        ['name' => 'unique_test_field_and_id_key__key', 'unique' => TRUE, 'key' => ['test_field' => 1, 'id' => 1]],
      ],
    ];

    $add_unique_key_base_4_with_embedded_5_and_field_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'unique keys' => ['unique_id_key' => ['id']],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.unique_id_key__key', 'unique' => TRUE, 'key' => ['test_table5.id' => 1]],
      ],
    ];

    $add_unique_key_base_4_with_embedded_5_and_field_id_and_test_field_string = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'unique keys' => ['unique_id_and_test_field_string_key' => ['id', 'test_field_string']],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.unique_id_and_test_field_string_key__key', 'unique' => TRUE, 'key' => ['test_table5.id' => 1, 'test_table5.test_field_string' => 1]],
      ],
    ];

    $add_unique_key_base_4_with_embedded_5_and_field_test_field_string_and_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'unique keys' => ['unique_test_field_string_and_id_key' => ['test_field_string', 'id']],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.unique_test_field_string_and_id_key__key', 'unique' => TRUE, 'key' => ['test_table5.test_field_string' => 1, 'test_table5.id' => 1]],
      ],
    ];

    $add_unique_key_base_6_with_embedded_4_and_field_id_and_test_field = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_field' => ['test_field'],
          'unique_id_and_test_field_key' => ['id', 'test_field'],
        ],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
        ['name' => 'test_table4.unique_id_and_test_field_key__key', 'unique' => TRUE, 'key' => ['test_table4.id' => 1, 'test_table4.test_field' => 1]],
      ],
    ];

    $add_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_1 = [
      'schema' => [
        'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'default' => NULL,
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
            'description' => 'Schema column description for string.',
          ],
          'test_field_string_ascii' => [
            'type' => 'varchar_ascii',
            'length' => 255,
            'description' => 'Schema column description for ASCII string.',
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_field' => ['test_field'],
          'unique_id_and_test_field_string_key' => ['id', 'test_field_string'],
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
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'unique_id_and_test_field_string_key__key', 'unique' => TRUE, 'key' => ['id' => 1, 'test_field_string' => 1]],
      ],
    ];

    $add_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_2 = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
            'type' => 'numeric',
            'default' => 4,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_fields_not_null' => ['test_field_int_not_null', 'test_field_float_not_null', 'test_field_numeric_not_null'],
          'test_fields_null' => ['test_field_int_null', 'test_field_float_null', 'test_field_numeric_null'],
          'unique_id_and_test_field_int_default_key' => ['id', 'test_field_int_default'],
        ],
        'indexes' => [
          'test_fields_default' => ['test_field_int_default', 'test_field_float_default', 'test_field_numeric_default'],
          'test_field_int_null' => ['test_field_int_null'],
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
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.unique_id_and_test_field_int_default_key__key', 'unique' => TRUE, 'key' => ['test_table2.id' => 1, 'test_table2.test_field_int_default' => 1]],
      ],
    ];

    $add_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_3 = [
      'schema' => [
        'description' => 'Schema table for testing varchar with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_varchar_null' => [
            'type' => 'varchar',
          ],
          'test_field_varchar_not_null' => [
            'type' => 'varchar',
            'not null' => TRUE,
          ],
          'test_field_varchar_default' => [
            'type' => 'varchar',
            'default' => 4,
          ],
          'test_field_text_null' => [
            'type' => 'text',
          ],
          'test_field_text_not_null' => [
            'type' => 'text',
            'not null' => TRUE,
          ],
          'test_field_text_default' => [
            'type' => 'text',
            'default' => 4,
          ],
        ],
        'unique keys' => ['unique_id_key' => ['id']],
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
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.unique_id_key__key', 'unique' => TRUE, 'key' => ['test_table2.test_table3.id' => 1]],
      ],
    ];

    $add_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_5 = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'unique keys' => ['unique_id_key' => ['id']],
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
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table5.unique_id_key__key', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table5.id' => 1]],
      ],
    ];

    return [
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'unique_id_key',
        ['id'],
        $add_unique_key_table_5_with_unique_id_key,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'unique_id_and_test_field_string_key',
        ['id', 'test_field_string'],
        $add_unique_key_table_5_with_unique_id_and_test_field_string_key,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'unique_test_field_string_and_id_key',
        ['test_field_string', 'id'],
        $add_unique_key_table_5_with_unique_test_field_string_and_id_key,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'unique_id_and_test_field_key',
        ['id', 'test_field'],
        $add_unique_key_table_4_with_unique_id_and_test_field_key,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'unique_test_field_and_id_key',
        ['test_field', 'id'],
        $add_unique_key_table_4_with_unique_test_field_and_id_key,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        'unique_id_key',
        ['id'],
        $add_unique_key_base_4_with_embedded_5_and_field_id,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        'unique_id_and_test_field_string_key',
        ['id', 'test_field_string'],
        $add_unique_key_base_4_with_embedded_5_and_field_id_and_test_field_string,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        'unique_test_field_string_and_id_key',
        ['test_field_string', 'id'],
        $add_unique_key_base_4_with_embedded_5_and_field_test_field_string_and_id,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4]],
        static::TEST_TABLE4['name'],
        'unique_id_and_test_field_key',
        ['id', 'test_field'],
        $add_unique_key_base_6_with_embedded_4_and_field_id_and_test_field,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE1['name'],
        'unique_id_and_test_field_string_key',
        ['id', 'test_field_string'],
        $add_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_1,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE2['name'],
        'unique_id_and_test_field_int_default_key',
        ['id', 'test_field_int_default'],
        $add_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_2,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE3['name'],
        'unique_id_key',
        ['id'],
        $add_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_3,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE5['name'],
        'unique_id_key',
        ['id'],
        $add_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_5,
      ],
    ];
  }

  /**
   * @covers ::addUniqueKey
   * @covers ::constraintExists
   * @covers ::uniqueKeyExists
   * @dataProvider providerAddUniqueKey
   */
  public function testAddUniqueKey($base_table_data, $embedded_tables_data, $table_name_to_add_unique_key, $unique_key_name, $unique_key_fields, $added_unique_key_table_data) {
    $this->assertFalse($this->schema->tableExists($base_table_data['name']), 'The table does not exist in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertFalse($this->schema->tableExists($embedded_table_data['name']), 'The embedded table does not exist in the MongoDB database.');
      }
    }
    $this->assertFalse($this->schema->uniqueKeyExists($table_name_to_add_unique_key, $unique_key_name), 'The table does not have the unique key.');

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

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table_name_to_add_unique_key);
    $this->assertFalse($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . $unique_key_name . '__key'), 'The embedded table does not have the unique key.');
    $this->assertFalse($this->schema->uniqueKeyExists($table_name_to_add_unique_key, $unique_key_name), 'The table does not have the unique key.');

    // Call the to be tested method: Schema::addUniqueKey().
    $this->schema->addUniqueKey($table_name_to_add_unique_key, $unique_key_name, $unique_key_fields);

    $this->assertTrue($this->schema->tableExists($base_table_data['name']), 'The table exists in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertTrue($this->schema->tableExists($embedded_table_data['name']), 'The embedded table exists in the MongoDB database.');
      }
    }
    $this->assertTrue($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . $unique_key_name . '__key'), 'The embedded table has the unique key.');
    $this->assertTrue($this->schema->uniqueKeyExists($table_name_to_add_unique_key, $unique_key_name), 'The table has the unique key.');

    // Check that the validation, schema or index data is what is it should be
    // for all table including the embedded table with the added unique key.
    $this->checkTableValidation($base_table_data['name'], $added_unique_key_table_data['validation']);
    if ($base_table_data['name'] == $table_name_to_add_unique_key) {
      $this->checkTableSchema($base_table_data['name'], $added_unique_key_table_data['schema']);
    }
    else {
      $this->checkTableSchema($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_add_unique_key) {
          $this->checkTableSchema($embedded_table_data['name'], $added_unique_key_table_data['schema']);
        }
        else {
          $embedded_table_data['schema']['embedded_to_table'] = $parent_table_name;
          $this->checkTableSchema($embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    $this->checkTableIndexes($base_table_data['name'], $base_table_data['schema']);
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
      }
    }
    $this->checkExpectedIndexesAgainstDatabase($base_table_data['name'], $added_unique_key_table_data['indexes']);

    // Update the embedded_tables_data with adding a unique key to an embedded
    // table.
    if ($base_table_data['name'] == $table_name_to_add_unique_key) {
      $base_table_data['schema'] = $added_unique_key_table_data['schema'];
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $key => $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_add_unique_key) {
          $embedded_tables_data[$parent_table_name][$key]['schema'] = $added_unique_key_table_data['schema'];
        }
      }
    }
    $this->checkTableNumberOfIndexes($base_table_data, $embedded_tables_data);
  }

  /**
   * @covers ::addUniqueKey
   */
  public function testAddUniqueKeyForTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');

    // If we try to add a unique key to a table that does not exist an exception
    // should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->addUniqueKey(static::TEST_TABLE5['name'], 'unique_id_key', ['id']);
  }

  /**
   * @covers ::addUniqueKey
   * @covers ::uniqueKeyExists
   */
  public function testAddUniqueKeyForUniqueKeyExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE4['name'], 'test_field__key'), 'The table has the unique key.');
    $this->assertTrue($this->schema->uniqueKeyExists(static::TEST_TABLE4['name'], 'test_field'), 'The table has the unique key.');

    // If we try to add a unique key to a table that has a unique key an
    // exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addUniqueKey(static::TEST_TABLE4['name'], 'test_field', ['test_field']);
  }

  /**
   * @covers ::addUniqueKey
   * @covers ::primaryKeyExists
   */
  public function testAddUniqueKeyForPrimaryKeyExistsOnTheSameFields() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE4['name'], '__pkey'), 'The table has a primary key.');
    $this->assertTrue($this->schema->primaryKeyExists(static::TEST_TABLE4['name']), 'The table has a primary key.');

    // If we try to add an unique key to a table that has a primary key on the
    // same fields an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addUniqueKey(static::TEST_TABLE4['name'], 'unique_id_key', ['id']);
  }

  /**
   * @covers ::addUniqueKey
   * @covers ::uniqueKeyExists
   */
  public function testAddUniqueKeyForUniqueKeyExistsOnTheSameFields() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE4['name'], 'test_field__key'), 'The table has the unique key.');
    $this->assertTrue($this->schema->uniqueKeyExists(static::TEST_TABLE4['name'], 'test_field'), 'The table has the unique key.');

    // If we try to add an unique key to a table that has an unique index on the
    // same fields an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addUniqueKey(static::TEST_TABLE4['name'], 'unique_test_field_key', ['test_field']);
  }

  /**
   * @covers ::addUniqueKey
   */
  public function testAddUniqueKeyForEmbeddedUniqueKeyExists() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE5['name'], static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table exists in the MongoDB database.');

    // If we try to add an unique key to a table that has an unique key an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addUniqueKey(static::TEST_TABLE4['name'], 'test_field', ['test_field']);
  }

  /**
   * @covers ::addUniqueKey
   * @covers ::primaryKeyExists
   */
  public function testAddUniqueKeyForEmbeddedPrimaryKeyExistsOnTheSameFields() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE5['name'], static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table exists in the MongoDB database.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE5['name'], static::TEST_TABLE4['name'] . '.__pkey'), 'The embedded table has a primary key.');
    $this->assertTrue($this->schema->primaryKeyExists(static::TEST_TABLE4['name']), 'The table has a primary key.');

    // If we try to add an unique key to an embedded table that has a primary
    // key on the same fields an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addUniqueKey(static::TEST_TABLE4['name'], 'unique_id_key', ['id']);
  }

  /**
   * @covers ::addUniqueKey
   * @covers ::uniqueKeyExists
   */
  public function testAddUniqueKeyForEmbeddedUniqueKeyExistsOnTheSameFields() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE5['name'], static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table exists in the MongoDB database.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE5['name'], static::TEST_TABLE4['name'] . '.test_field__key'), 'The embedded table has the unique key.');
    $this->assertTrue($this->schema->uniqueKeyExists(static::TEST_TABLE4['name'], 'test_field'), 'The table has the unique key.');

    // If we try to add an unique key to an embedded table that has an unique
    // index on the same fields an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addUniqueKey(static::TEST_TABLE4['name'], 'unique_test_field_key', ['test_field']);
  }

  /**
   * Data provider for testDropUniqueKey().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the name of the unique key that is to be dropped.
   *     - the expected schema for the table after dropping the unique key.
   */
  public static function providerDropUniqueKey() {
    $drop_unique_key_test_field_table_1_data = [
      'schema' => [
        'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'default' => NULL,
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
            'description' => 'Schema column description for string.',
          ],
          'test_field_string_ascii' => [
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
      ],
    ];

    $drop_unique_key_test_fields_not_null_table_2_data = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
            'type' => 'numeric',
            'default' => 4,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_fields_null' => ['test_field_int_null', 'test_field_float_null', 'test_field_numeric_null']],
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
        ['name' => 'test_fields_null__key', 'unique' => TRUE, 'key' => ['test_field_int_null' => 1, 'test_field_float_null' => 1, 'test_field_numeric_null' => 1]],
        ['name' => 'test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_field_int_default' => 1, 'test_field_float_default' => 1, 'test_field_numeric_default' => 1]],
        ['name' => 'test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_field_int_null' => 1]],
      ],
    ];

    $drop_unique_key_test_fields_null_table_2_data = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
            'type' => 'numeric',
            'default' => 4,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_fields_not_null' => ['test_field_int_not_null', 'test_field_float_not_null', 'test_field_numeric_not_null']],
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
        ['name' => 'test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_field_int_default' => 1, 'test_field_float_default' => 1, 'test_field_numeric_default' => 1]],
        ['name' => 'test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_field_int_null' => 1]],
      ],
    ];

    $drop_unique_key_test_field_table_4_data = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field' => [
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
          ['test_field' => ['$type' => 'int']],
          ['test_field' => ['$exists' => TRUE]],
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
      ],
    ];

    $drop_unique_key_base_6_with_embedded_1_unique_key_test_field = [
      'schema' => [
        'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'default' => NULL,
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
            'description' => 'Schema column description for string.',
          ],
          'test_field_string_ascii' => [
            'type' => 'varchar_ascii',
            'length' => 255,
            'description' => 'Schema column description for ASCII string.',
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
                  ['test_table1.id' => ['$exists' => FALSE]],
                  ['test_table1.test_field' => ['$exists' => FALSE]],
                  ['test_table1.test_field_string' => ['$exists' => FALSE]],
                  ['test_table1.test_field_string_ascii' => ['$exists' => FALSE]],
                ],
              ],
              [
                '$and' => [
                  ['test_table1.id' => ['$type' => 'int']],
                  ['test_table1.id' => ['$exists' => TRUE]],
                  ['test_table1.test_field' => ['$type' => 'int']],
                  ['test_table1.test_field' => ['$exists' => TRUE]],
                  ['test_table1.test_field_string' => ['$type' => 'string']],
                  ['test_table1.test_field_string' => ['$exists' => TRUE]],
                  [
                    '$or' => [
                      ['test_table1.test_field_string_ascii' => ['$type' => 'string']],
                      ['test_table1.test_field_string_ascii' => ['$exists' => FALSE]],
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
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table1.__pkey', 'unique' => TRUE, 'key' => ['test_table1.id' => 1]],
      ],
    ];

    $drop_unique_key_base_6_with_embedded_2_unique_key_test_fields_not_null = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
            'type' => 'numeric',
            'default' => 4,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_fields_null' => ['test_field_int_null', 'test_field_float_null', 'test_field_numeric_null']],
        'indexes' => [
          'test_fields_default' => ['test_field_int_default', 'test_field_float_default', 'test_field_numeric_default'],
          'test_field_int_null' => ['test_field_int_null'],
        ],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
      ],
    ];

    $drop_unique_key_base_6_with_embedded_2_unique_key_test_fields_null = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
            'type' => 'numeric',
            'default' => 4,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => ['test_fields_not_null' => ['test_field_int_not_null', 'test_field_float_not_null', 'test_field_numeric_not_null']],
        'indexes' => [
          'test_fields_default' => ['test_field_int_default', 'test_field_float_default', 'test_field_numeric_default'],
          'test_field_int_null' => ['test_field_int_null'],
        ],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
      ],
    ];

    $drop_unique_key_base_6_with_embedded_4_unique_key_test_field = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field' => [
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
      ],
    ];

    $drop_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_1 = [
      'schema' => [
        'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'default' => NULL,
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
            'description' => 'Schema column description for string.',
          ],
          'test_field_string_ascii' => [
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
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
      ],
    ];

    $drop_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_2 = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
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
          'test_field_int_null' => ['test_field_int_null'],
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
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
      ],
    ];

    $drop_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_4 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field' => [
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
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
      ],
    ];

    return [
      [
        static::TEST_TABLE1,
        [],
        static::TEST_TABLE1['name'],
        'test_field',
        $drop_unique_key_test_field_table_1_data,
      ],
      [
        static::TEST_TABLE2,
        [],
        static::TEST_TABLE2['name'],
        'test_fields_not_null',
        $drop_unique_key_test_fields_not_null_table_2_data,
      ],
      [
        static::TEST_TABLE2,
        [],
        static::TEST_TABLE2['name'],
        'test_fields_null',
        $drop_unique_key_test_fields_null_table_2_data,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'test_field',
        $drop_unique_key_test_field_table_4_data,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE1]],
        static::TEST_TABLE1['name'],
        'test_field',
        $drop_unique_key_base_6_with_embedded_1_unique_key_test_field,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE2]],
        static::TEST_TABLE2['name'],
        'test_fields_not_null',
        $drop_unique_key_base_6_with_embedded_2_unique_key_test_fields_not_null,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE2]],
        static::TEST_TABLE2['name'],
        'test_fields_null',
        $drop_unique_key_base_6_with_embedded_2_unique_key_test_fields_null,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4]],
        static::TEST_TABLE4['name'],
        'test_field',
        $drop_unique_key_base_6_with_embedded_4_unique_key_test_field,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE1['name'],
        'test_field',
        $drop_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_1,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE2['name'],
        'test_fields_null',
        $drop_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_2,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        'test_field',
        $drop_unique_key_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_4,
      ],
    ];
  }

  /**
   * @covers ::dropUniqueKey
   * @covers ::constraintExists
   * @covers ::uniqueKeyExists
   * @dataProvider providerDropUniqueKey
   */
  public function testDropUniqueKey($base_table_data, $embedded_tables_data, $table_name_to_drop_unique_key, $unique_key_name, $dropped_unique_key_table_data) {
    $this->assertFalse($this->schema->tableExists($base_table_data['name']), 'The table does not exist in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertFalse($this->schema->tableExists($embedded_table_data['name']), 'The embedded table does not exist in the MongoDB database.');
      }
    }
    $this->assertFalse($this->schema->uniqueKeyExists($table_name_to_drop_unique_key, $unique_key_name), 'The table does not have the unique key.');

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

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table_name_to_drop_unique_key);
    $this->assertTrue($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . $unique_key_name . '__key'), 'The embedded table has the unique key.');
    $this->assertTrue($this->schema->uniqueKeyExists($table_name_to_drop_unique_key, $unique_key_name), 'The table has the unique key.');

    // Call the to be tested method: Schema::dropUniqueKey().
    $this->schema->dropUniqueKey($table_name_to_drop_unique_key, $unique_key_name);

    $this->assertTrue($this->schema->tableExists($base_table_data['name']), 'The table exists in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertTrue($this->schema->tableExists($embedded_table_data['name']), 'The embedded table exists in the MongoDB database.');
      }
    }
    $this->assertFalse($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . $unique_key_name . '__key'), 'The embedded table does not have the unique key.');
    $this->assertFalse($this->schema->uniqueKeyExists($table_name_to_drop_unique_key, $unique_key_name), 'The table does not have the unique key.');

    // Check that the validation, schema or index data is what is it should be
    // for all table including the embedded table without the dropped unique key.
    $this->checkTableValidation($base_table_data['name'], $dropped_unique_key_table_data['validation']);
    if ($base_table_data['name'] == $table_name_to_drop_unique_key) {
      $this->checkTableSchema($base_table_data['name'], $dropped_unique_key_table_data['schema']);
    }
    else {
      $this->checkTableSchema($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_unique_key) {
          $this->checkTableSchema($embedded_table_data['name'], $dropped_unique_key_table_data['schema']);
        }
        else {
          $embedded_table_data['schema']['embedded_to_table'] = $parent_table_name;
          $this->checkTableSchema($embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    if ($base_table_data['name'] == $table_name_to_drop_unique_key) {
      $this->checkTableIndexes($base_table_data['name'], $dropped_unique_key_table_data['schema']);
    }
    else {
      $this->checkTableIndexes($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_unique_key) {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $dropped_unique_key_table_data['schema']);
        }
        else {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    $this->checkExpectedIndexesAgainstDatabase($base_table_data['name'], $dropped_unique_key_table_data['indexes']);

    // Update the embedded_tables_data with adding a unique key to an embedded
    // table.
    if ($base_table_data['name'] == $table_name_to_drop_unique_key) {
      $base_table_data['schema'] = $dropped_unique_key_table_data['schema'];
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $key => $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_unique_key) {
          $embedded_tables_data[$parent_table_name][$key]['schema'] = $dropped_unique_key_table_data['schema'];
        }
      }
    }
    $this->checkTableNumberOfIndexes($base_table_data, $embedded_tables_data);
  }

  /**
   * @covers ::dropUniqueKey
   */
  public function testDropUniqueKeyForTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE2['name']), 'The table does not exist in the MongoDB database.');

    // If we try to drop a unique key to a table that does not exist an
    // exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->dropUniqueKey(static::TEST_TABLE2['name'], 'test_fields_not_null');
  }

  /**
   * @covers ::dropUniqueKey
   * @covers ::uniqueKeyExists
   */
  public function testDropUniqueKeyForUniqueKeyDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table exists in the MongoDB database.');
    $this->assertFalse($this->schema->constraintExists(static::TEST_TABLE5['name'], 'does_not_exist__key'), 'The table does not have the unique key.');
    $this->assertFalse($this->schema->uniqueKeyExists(static::TEST_TABLE5['name'], 'does_not_exist'), 'The table does not have the unique key.');

    // If we try to drop a non existent unique key on a table an exception
    // should be thrown.
    $this->assertFalse($this->schema->dropUniqueKey(static::TEST_TABLE5['name'], 'does_not_exist__key'), 'The table does not have the unique key.');
  }

  /**
   * @covers ::dropUniqueKey
   * @covers ::uniqueKeyExists
   */
  public function testDropUniqueKeyForEmbeddedUniqueKeyDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE6['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE6['name'], static::TEST_TABLE6['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE6['name'], static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE6['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table exists in the MongoDB database.');
    $this->assertFalse($this->schema->constraintExists(static::TEST_TABLE6['name'], static::TEST_TABLE4['name'] . '.unique_does_not_exist_key__key'), 'The embedded table does not have the unique key.');
    $this->assertFalse($this->schema->uniqueKeyExists(static::TEST_TABLE4['name'], 'unique_does_not_exist_key'), 'The table does not have the unique key.');

    // If we try to drop a non existent unique key from a table the method
    // should return false.
    $this->assertFalse($this->schema->dropUniqueKey(static::TEST_TABLE4['name'], 'unique_does_not_exist_key'), 'The embedded table does not have the unique key.');
  }

  /**
   * Data provider for testAddIndex().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the array of table data to use as embedded tables (the name, the
   *       schema and the validation).
   *     - the name to used for creating the index.
   *     - the array of field names to used for creating the index.
   *     - the expected schema for the table after adding the index.
   */
  public static function providerAddIndex() {
    $add_index_table_5_with_index_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'indexes' => ['index_id' => ['id']],
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
        ['name' => 'index_id__idx', 'unique' => FALSE, 'key' => ['id' => 1]],
      ],
    ];

    $add_index_table_5_with_index_id_and_test_field_string = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'indexes' => ['index_id_and_test_field_string' => ['id', 'test_field_string']],
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
        ['name' => 'index_id_and_test_field_string__idx', 'unique' => FALSE, 'key' => ['id' => 1, 'test_field_string' => 1]],
      ],
    ];

    $add_index_table_5_with_index_test_field_string_and_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'indexes' => ['index_test_field_string_and_id' => ['test_field_string', 'id']],
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
        ['name' => 'index_test_field_string_and_id__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1, 'id' => 1]],
      ],
    ];

    $add_index_table_4_with_index_id_and_test_field = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
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
        'indexes' => ['index_id_and_test_field' => ['id', 'test_field']],
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
        ['name' => 'index_id_and_test_field__idx', 'unique' => FALSE, 'key' => ['id' => 1, 'test_field' => 1]],
      ],
    ];

    $add_index_table_4_with_index_test_field_and_id = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
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
        'indexes' => ['index_test_field_and_id' => ['test_field', 'id']],
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
        ['name' => 'index_test_field_and_id__idx', 'unique' => FALSE, 'key' => ['test_field' => 1, 'id' => 1]],
      ],
    ];

    $add_index_base_4_with_embedded_5_and_field_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'indexes' => ['index_id' => ['id']],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.index_id__idx', 'unique' => FALSE, 'key' => ['test_table5.id' => 1]],
      ],
    ];

    $add_index_base_4_with_embedded_5_and_field_id_and_test_field_string = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'indexes' => ['index_id_and_test_field_string' => ['id', 'test_field_string']],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.index_id_and_test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table5.id' => 1, 'test_table5.test_field_string' => 1]],
      ],
    ];

    $add_index_base_4_with_embedded_5_and_field_test_field_string_and_id = [
      'schema' => [
        'description' => 'Schema table with no indexes.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
          ],
        ],
        'indexes' => ['index_test_field_string_and_id' => ['test_field_string', 'id']],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field__key', 'unique' => TRUE, 'key' => ['test_field' => 1]],
        ['name' => 'test_table5.index_test_field_string_and_id__idx', 'unique' => FALSE, 'key' => ['test_table5.test_field_string' => 1, 'test_table5.id' => 1]],
      ],
    ];

    $add_index_base_6_with_embedded_4_and_field_id_and_test_field = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
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
        'indexes' => ['index_id_and_test_field' => ['id', 'test_field']],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table4.id' => 1]],
        ['name' => 'test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table4.test_field' => 1]],
        ['name' => 'test_table4.index_id_and_test_field__idx', 'unique' => FALSE, 'key' => ['test_table4.id' => 1, 'test_table4.test_field' => 1]],
      ],
    ];

    $add_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_1 = [
      'schema' => [
        'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'default' => NULL,
            'not null' => TRUE,
          ],
          'test_field' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
          ],
          'test_field_string' => [
            'type' => 'varchar',
            'length' => 20,
            'not null' => TRUE,
            'default' => "'\"Quoting fun!'\"",
            'description' => 'Schema column description for string.',
          ],
          'test_field_string_ascii' => [
            'type' => 'varchar_ascii',
            'length' => 255,
            'description' => 'Schema column description for ASCII string.',
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_field' => ['test_field'],
        ],
        'indexes' => [
          'index_id_and_test_field_string' => ['id', 'test_field_string'],
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
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'index_id_and_test_field_string__idx', 'unique' => FALSE, 'key' => ['id' => 1, 'test_field_string' => 1]],
      ],
    ];

    $add_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_2 = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
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
          'index_id_and_test_field_int_default' => ['id', 'test_field_int_default'],
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
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.index_id_and_test_field_int_default__idx', 'unique' => FALSE, 'key' => ['test_table2.id' => 1, 'test_table2.test_field_int_default' => 1]],
      ],
    ];

    $add_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_4 = [
      'schema' => [
        'description' => 'Schema table with primary and unique key.',
        'fields' => [
          'id' => [
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
        'indexes' => [
          'index_id_and_test_field' => ['id', 'test_field'],
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
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table4.index_id_and_test_field__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table4.id' => 1, 'test_table2.test_table4.test_field' => 1]],
      ],
    ];

    $add_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_6 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_string' => [
            'type' => 'varchar',
          ],
        ],
        'primary key' => ['id'],
        'indexes' => [
          'test_field_string' => ['test_field_string'],
          'index_id_and_test_field_string' => ['id', 'test_field_string'],
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
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.index_id_and_test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.id' => 1, 'test_table2.test_table3.test_table6.test_field_string' => 1]],
      ],
    ];

    return [
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'index_id',
        ['id'],
        $add_index_table_5_with_index_id,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'index_id_and_test_field_string',
        ['id', 'test_field_string'],
        $add_index_table_5_with_index_id_and_test_field_string,
      ],
      [
        static::TEST_TABLE5,
        [],
        static::TEST_TABLE5['name'],
        'index_test_field_string_and_id',
        ['test_field_string', 'id'],
        $add_index_table_5_with_index_test_field_string_and_id,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'index_id_and_test_field',
        ['id', 'test_field'],
        $add_index_table_4_with_index_id_and_test_field,
      ],
      [
        static::TEST_TABLE4,
        [],
        static::TEST_TABLE4['name'],
        'index_test_field_and_id',
        ['test_field', 'id'],
        $add_index_table_4_with_index_test_field_and_id,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        'index_id',
        ['id'],
        $add_index_base_4_with_embedded_5_and_field_id,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        'index_id_and_test_field_string',
        ['id', 'test_field_string'],
        $add_index_base_4_with_embedded_5_and_field_id_and_test_field_string,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE5]],
        static::TEST_TABLE5['name'],
        'index_test_field_string_and_id',
        ['test_field_string', 'id'],
        $add_index_base_4_with_embedded_5_and_field_test_field_string_and_id,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE4]],
        static::TEST_TABLE4['name'],
        'index_id_and_test_field',
        ['id', 'test_field'],
        $add_index_base_6_with_embedded_4_and_field_id_and_test_field,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE1['name'],
        'index_id_and_test_field_string',
        ['id', 'test_field_string'],
        $add_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_1,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE2['name'],
        'index_id_and_test_field_int_default',
        ['id', 'test_field_int_default'],
        $add_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_2,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE4['name'],
        'index_id_and_test_field',
        ['id', 'test_field'],
        $add_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_4,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE6['name'],
        'index_id_and_test_field_string',
        ['id', 'test_field_string'],
        $add_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_6,
      ],
    ];
  }

  /**
   * @covers ::addIndex
   * @covers ::constraintExists
   * @covers ::indexExists
   * @dataProvider providerAddIndex
   */
  public function testAddIndex($base_table_data, $embedded_tables_data, $table_name_to_add_index, $index_name, $index_fields, $added_index_table_data) {
    $this->assertFalse($this->schema->tableExists($base_table_data['name']), 'The table does not exist in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertFalse($this->schema->tableExists($embedded_table_data['name']), 'The embedded table does not exist in the MongoDB database.');
      }
    }
    $this->assertFalse($this->schema->indexExists($table_name_to_add_index, $index_name), 'The embedded table does not have the index.');

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

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table_name_to_add_index);
    $this->assertFalse($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . $index_name . '__idx'), 'The embedded table does not have the index.');
    $this->assertFalse($this->schema->indexExists($table_name_to_add_index, $index_name), 'The embedded table does not have the index.');

    // Call the to be tested method: Schema::addIndex().
    $this->schema->addIndex($table_name_to_add_index, $index_name, $index_fields, []);

    $this->assertTrue($this->schema->tableExists($base_table_data['name']), 'The table exists in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertTrue($this->schema->tableExists($embedded_table_data['name']), 'The embedded table exists in the MongoDB database.');
      }
    }
    $this->assertTrue($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . $index_name . '__idx'), 'The embedded table has the index.');
    $this->assertTrue($this->schema->indexExists($table_name_to_add_index, $index_name), 'The embedded table has the index.');

    // Check that the validation, schema or index data is what is it should be
    // for all table including the embedded table with the added index.
    $this->checkTableValidation($base_table_data['name'], $added_index_table_data['validation']);
    if ($base_table_data['name'] == $table_name_to_add_index) {
      $this->checkTableSchema($base_table_data['name'], $added_index_table_data['schema']);
    }
    else {
      $this->checkTableSchema($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_add_index) {
          $this->checkTableSchema($embedded_table_data['name'], $added_index_table_data['schema']);
        }
        else {
          $embedded_table_data['schema']['embedded_to_table'] = $parent_table_name;
          $this->checkTableSchema($embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    $this->checkTableIndexes($base_table_data['name'], $base_table_data['schema']);
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_add_index) {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $added_index_table_data['schema']);
        }
        else {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    $this->checkExpectedIndexesAgainstDatabase($base_table_data['name'], $added_index_table_data['indexes']);

    // Update the embedded_tables_data with adding an index to an embedded
    // table.
    if ($base_table_data['name'] == $table_name_to_add_index) {
      $base_table_data['schema'] = $added_index_table_data['schema'];
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $key => $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_add_index) {
          $embedded_tables_data[$parent_table_name][$key]['schema'] = $added_index_table_data['schema'];
        }
      }
    }
    $this->checkTableNumberOfIndexes($base_table_data, $embedded_tables_data);
  }

  /**
   * @covers ::addIndex
   */
  public function testAddIndexForTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');

    // If we try to add an index to a table that does not exist an exception
    // should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->addIndex(static::TEST_TABLE5['name'], 'index_id', ['id'], []);
  }

  /**
   * @covers ::addIndex
   * @covers ::indexExists
   */
  public function testAddIndexForIndexExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE6['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE6['name'], static::TEST_TABLE6['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE6['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE6['name'], 'test_field_string__idx'), 'The table has the index.');
    $this->assertTrue($this->schema->indexExists(static::TEST_TABLE6['name'], 'test_field_string'), 'The table has the index.');

    // If we try to add an index to a table that has an index an exception
    // should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addIndex(static::TEST_TABLE6['name'], 'test_field_string', ['test_field_string'], []);
  }

  /**
   * @covers ::addIndex
   * @covers ::indexExists
   */
  public function testAddIndexForIndexExistsOnTheSameFields() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE6['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE6['name'], static::TEST_TABLE6['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE6['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE6['name'], 'test_field_string__idx'), 'The table has the index.');
    $this->assertTrue($this->schema->indexExists(static::TEST_TABLE6['name'], 'test_field_string'), 'The table has the index.');

    // If we try to add an index to a table that has an non-unique index on the
    // same fields an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addIndex(static::TEST_TABLE6['name'], 'index_test_field_string', ['test_field_string'], []);
  }

  /**
   * @covers ::addIndex
   */
  public function testAddIndexForEmbeddedTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');

    // If we try to rename an embedded table on a non existent embedded table an
    // exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->addIndex(static::TEST_TABLE5['name'], 'index_id', ['id'], []);
  }

  /**
   * @covers ::addIndex
   * @covers ::indexExists
   */
  public function testAddIndexForEmbeddedIndexExists() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE6['name']), 'The embedded table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE5['name'], static::TEST_TABLE6['name'], static::TEST_TABLE6['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE6['name']), 'The embedded table exists in the MongoDB database.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE5['name'], static::TEST_TABLE6['name'] . '.test_field_string__idx'), 'The embedded table has the index.');
    $this->assertTrue($this->schema->indexExists(static::TEST_TABLE6['name'], 'test_field_string'), 'The embedded table has the index.');

    // If we try to add an index to a table that has an index an exception
    // should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addIndex(static::TEST_TABLE6['name'], 'test_field_string', ['test_field_string'], []);
  }

  /**
   * @covers ::addIndex
   * @covers ::indexExists
   */
  public function testAddIndexForEmbeddedIndexExistsOnTheSameFields() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE6['name']), 'The embedded table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE5['name'], static::TEST_TABLE6['name'], static::TEST_TABLE6['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE6['name']), 'The embedded table exists in the MongoDB database.');
    $this->assertTrue($this->schema->constraintExists(static::TEST_TABLE5['name'], static::TEST_TABLE6['name'] . '.test_field_string__idx'), 'The embedded table has the index.');
    $this->assertTrue($this->schema->indexExists(static::TEST_TABLE6['name'], 'test_field_string'), 'The embedded table has the index.');

    // If we try to add an index to an embedded table that has an index on the
    // same fields an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectExistsException');
    $this->schema->addIndex(static::TEST_TABLE6['name'], 'index_test_field_string', ['test_field_string'], []);
  }

  /**
   * Data provider for testDropIndex().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the name of the index that is to be dropped.
   *     - the expected schema for the table after dropping the index.
   */
  public static function providerDropIndex() {
    $drop_index_test_fields_default_table_2_data = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
            'type' => 'numeric',
            'default' => 4,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_fields_not_null' => ['test_field_int_not_null', 'test_field_float_not_null', 'test_field_numeric_not_null'],
          'test_fields_null' => ['test_field_int_null', 'test_field_float_null', 'test_field_numeric_null'],
        ],
        'indexes' => ['test_field_int_null' => ['test_field_int_null']],
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
        ['name' => 'test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_field_int_null' => 1]],
      ],
    ];

    $drop_index_test_field_int_null_table_2_data = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
            'type' => 'numeric',
            'default' => 4,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_fields_not_null' => ['test_field_int_not_null', 'test_field_float_not_null', 'test_field_numeric_not_null'],
          'test_fields_null' => ['test_field_int_null', 'test_field_float_null', 'test_field_numeric_null'],
        ],
        'indexes' => ['test_fields_default' => ['test_field_int_default', 'test_field_float_default', 'test_field_numeric_default']],
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
      ],
    ];

    $drop_index_test_field_string_table_6_data = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_string' => [
            'type' => 'varchar',
          ],
        ],
        'primary key' => ['id'],
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
      ],
    ];

    $drop_index_base_6_with_embedded_2_index_test_fields_default = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
            'type' => 'numeric',
            'default' => 4,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_fields_not_null' => ['test_field_int_not_null', 'test_field_float_not_null', 'test_field_numeric_not_null'],
          'test_fields_null' => ['test_field_int_null', 'test_field_float_null', 'test_field_numeric_null'],
        ],
        'indexes' => ['test_field_int_null' => ['test_field_int_null']],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
      ],
    ];

    $drop_index_base_6_with_embedded_2_index_test_field_int_null = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
            'type' => 'numeric',
            'default' => 4,
          ],
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'test_fields_not_null' => ['test_field_int_not_null', 'test_field_float_not_null', 'test_field_numeric_not_null'],
          'test_fields_null' => ['test_field_int_null', 'test_field_float_null', 'test_field_numeric_null'],
        ],
        'indexes' => ['test_fields_default' => ['test_field_int_default', 'test_field_float_default', 'test_field_numeric_default']],
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
        ],
      ],
      'indexes' => [
        ['name' => '_id_', 'unique' => FALSE, 'key' => ['_id' => 1]],
        ['name' => '__pkey', 'unique' => TRUE, 'key' => ['id' => 1]],
        ['name' => 'test_field_string__idx', 'unique' => FALSE, 'key' => ['test_field_string' => 1]],
        ['name' => 'test_table2.__pkey', 'unique' => TRUE, 'key' => ['test_table2.id' => 1]],
        ['name' => 'test_table2.test_fields_not_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_not_null' => 1, 'test_table2.test_field_float_not_null' => 1, 'test_table2.test_field_numeric_not_null' => 1]],
        ['name' => 'test_table2.test_fields_null__key', 'unique' => TRUE, 'key' => ['test_table2.test_field_int_null' => 1, 'test_table2.test_field_float_null' => 1, 'test_table2.test_field_numeric_null' => 1]],
        ['name' => 'test_table2.test_fields_default__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_default' => 1, 'test_table2.test_field_float_default' => 1, 'test_table2.test_field_numeric_default' => 1]],
      ],
    ];

    $drop_index_base_4_with_embedded_6_index_test_field_string = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_string' => [
            'type' => 'varchar',
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
      ],
    ];

    $drop_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_2 = [
      'schema' => [
        'description' => 'Schema table for testing integer with null, not null and default values.',
        'fields' => [
          'id' => [
            'type' => 'serial',
          ],
          'test_field_int_null' => [
            'type' => 'int',
            'not null' => FALSE,
          ],
          'test_field_int_not_null' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_int_default' => [
            'type' => 'int',
            'default' => 4,
          ],
          'test_field_float_null' => [
            'type' => 'float',
            'not null' => FALSE,
          ],
          'test_field_float_not_null' => [
            'type' => 'float',
            'not null' => TRUE,
          ],
          'test_field_float_default' => [
            'type' => 'float',
            'default' => 4,
          ],
          'test_field_numeric_null' => [
            'type' => 'numeric',
            'not null' => FALSE,
          ],
          'test_field_numeric_not_null' => [
            'type' => 'numeric',
            'not null' => TRUE,
          ],
          'test_field_numeric_default' => [
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
          'test_field_int_null' => ['test_field_int_null'],
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
        ['name' => 'test_table2.test_field_int_null__idx', 'unique' => FALSE, 'key' => ['test_table2.test_field_int_null' => 1]],
        ['name' => 'test_table2.test_table4.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table4.id' => 1]],
        ['name' => 'test_table2.test_table4.test_field__key', 'unique' => TRUE, 'key' => ['test_table2.test_table4.test_field' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.test_field_string__idx', 'unique' => FALSE, 'key' => ['test_table2.test_table3.test_table6.test_field_string' => 1]],
        ['name' => 'test_table2.test_table3.test_table6.__pkey', 'unique' => TRUE, 'key' => ['test_table2.test_table3.test_table6.id' => 1]],
      ],
    ];

    $drop_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_6 = [
      'schema' => [
        'description' => 'Schema table with primary and index.',
        'fields' => [
          'id' => [
            'type' => 'int',
            'not null' => TRUE,
          ],
          'test_field_string' => [
            'type' => 'varchar',
          ],
        ],
        'primary key' => ['id'],
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
      ],
    ];

    return [
      [
        static::TEST_TABLE2,
        [],
        static::TEST_TABLE2['name'],
        'test_fields_default',
        $drop_index_test_fields_default_table_2_data,
      ],
      [
        static::TEST_TABLE2,
        [],
        static::TEST_TABLE2['name'],
        'test_field_int_null',
        $drop_index_test_field_int_null_table_2_data,
      ],
      [
        static::TEST_TABLE6,
        [],
        static::TEST_TABLE6['name'],
        'test_field_string',
        $drop_index_test_field_string_table_6_data,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE2]],
        static::TEST_TABLE2['name'],
        'test_fields_default',
        $drop_index_base_6_with_embedded_2_index_test_fields_default,
      ],
      [
        static::TEST_TABLE6,
        [static::TEST_TABLE6['name'] => [static::TEST_TABLE2]],
        static::TEST_TABLE2['name'],
        'test_field_int_null',
        $drop_index_base_6_with_embedded_2_index_test_field_int_null,
      ],
      [
        static::TEST_TABLE4,
        [static::TEST_TABLE4['name'] => [static::TEST_TABLE6]],
        static::TEST_TABLE6['name'],
        'test_field_string',
        $drop_index_base_4_with_embedded_6_index_test_field_string,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE2['name'],
        'test_fields_default',
        $drop_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_2,
      ],
      [
        static::TEST_TABLE1,
        [static::TEST_TABLE1['name'] => [static::TEST_TABLE2], static::TEST_TABLE2['name'] => [static::TEST_TABLE3, static::TEST_TABLE4], static::TEST_TABLE3['name'] => [static::TEST_TABLE5, static::TEST_TABLE6]],
        static::TEST_TABLE6['name'],
        'test_field_string',
        $drop_index_base_1_embedded_2_and_3_4_on_2_and_5_6_on_3_on_table_6,
      ],
    ];
  }

  /**
   * @covers ::dropIndex
   * @covers ::constraintExists
   * @covers ::indexExists
   * @dataProvider providerDropIndex
   */
  public function testDropIndex($base_table_data, $embedded_tables_data, $table_name_to_drop_index, $index_name, $dropped_index_table_data) {
    $this->assertFalse($this->schema->tableExists($base_table_data['name']), 'The table does not exist in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertFalse($this->schema->tableExists($embedded_table_data['name']), 'The embedded table does not exist in the MongoDB database.');
      }
    }
    $this->assertFalse($this->schema->indexExists($table_name_to_drop_index, $index_name), 'The embedded table does not have the index.');

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

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table_name_to_drop_index);
    $this->assertTrue($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . $index_name . '__idx'), 'The embedded table has the index.');
    $this->assertTrue($this->schema->indexExists($table_name_to_drop_index, $index_name), 'The embedded table has the index.');

    // Call the to be tested method: Schema::dropIndex().
    $this->schema->dropIndex($table_name_to_drop_index, $index_name);

    $this->assertTrue($this->schema->tableExists($base_table_data['name']), 'The table exists in the MongoDB database.');
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->assertTrue($this->schema->tableExists($embedded_table_data['name']), 'The embedded table exists in the MongoDB database.');
      }
    }
    $this->assertFalse($this->schema->constraintExists($base_table_data['name'], $embedded_full_path . $index_name . '__idx'), 'The embedded table does not have the index.');
    $this->assertFalse($this->schema->indexExists($table_name_to_drop_index, $index_name), 'The embedded table does not have the index.');

    // Check that the validation, schema or index data is what is it should be
    // for all table including the embedded table without the dropped index.
    $this->checkTableValidation($base_table_data['name'], $dropped_index_table_data['validation']);
    if ($base_table_data['name'] == $table_name_to_drop_index) {
      $this->checkTableSchema($base_table_data['name'], $dropped_index_table_data['schema']);
    }
    else {
      $this->checkTableSchema($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_index) {
          $this->checkTableSchema($embedded_table_data['name'], $dropped_index_table_data['schema']);
        }
        else {
          $embedded_table_data['schema']['embedded_to_table'] = $parent_table_name;
          $this->checkTableSchema($embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    if ($base_table_data['name'] == $table_name_to_drop_index) {
      $this->checkTableIndexes($base_table_data['name'], $dropped_index_table_data['schema']);
    }
    else {
      $this->checkTableIndexes($base_table_data['name'], $base_table_data['schema']);
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_index) {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $dropped_index_table_data['schema']);
        }
        else {
          $this->checkEmbeddedTableIndexes($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
        }
      }
    }
    $this->checkExpectedIndexesAgainstDatabase($base_table_data['name'], $dropped_index_table_data['indexes']);

    // Update the embedded_tables_data with adding an index to an embedded
    // table.
    if ($base_table_data['name'] == $table_name_to_drop_index) {
      $base_table_data['schema'] = $dropped_index_table_data['schema'];
    }
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $key => $embedded_table_data) {
        if ($embedded_table_data['name'] == $table_name_to_drop_index) {
          $embedded_tables_data[$parent_table_name][$key]['schema'] = $dropped_index_table_data['schema'];
        }
      }
    }
    $this->checkTableNumberOfIndexes($base_table_data, $embedded_tables_data);
  }

  /**
   * @covers ::dropIndex
   * @covers ::indexExists
   */
  public function testDropIndexForIndexDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table exists in the MongoDB database.');
    $this->assertFalse($this->schema->constraintExists(static::TEST_TABLE5['name'], 'does_not_exist__idx'), 'The table does not have the index.');
    $this->assertFalse($this->schema->indexExists(static::TEST_TABLE5['name'], 'does_not_exist'), 'The table does not have the index.');

    // If we try to drop a non existent index on a table an exception should be
    // thrown.
    $this->assertFalse($this->schema->dropIndex(static::TEST_TABLE5['name'], 'does_not_exist'), 'The table does not have the index.');
  }

  /**
   * @covers ::dropIndex
   * @covers ::indexExists
   */
  public function testDropIndexForEmbeddedIndexDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE6['name']), 'The table does not exist in the MongoDB database.');
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE6['name'], static::TEST_TABLE6['schema']);
    $this->schema->createEmbeddedTable(static::TEST_TABLE6['name'], static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE6['name']), 'The table exists in the MongoDB database.');
    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The embedded table exists in the MongoDB database.');
    $this->assertFalse($this->schema->constraintExists(static::TEST_TABLE6['name'], static::TEST_TABLE4['name'] . '.index_does_not_exist__idx'), 'The embedded table does not have the index.');
    $this->assertFalse($this->schema->indexExists(static::TEST_TABLE4['name'], 'index_does_not_exist'), 'The embedded table does not have the index.');

    // If we try to drop a non existent index from a table the method should
    // return false.
    $this->assertFalse($this->schema->dropIndex(static::TEST_TABLE4['name'], 'index_does_not_exist'), 'The embedded table does not have the index.');
  }

  /**
   * Data provider for testFieldSetDefault().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the name of the field that has its default value changed.
   *     - the value to be used for setting the default value.
   *     - the expected schema for the table after setting the default value for
   *       the field.
   */
  public static function providerFieldSetDefault() {
    $field_set_default_table_4_field_id_value_0_schema = [
      'description' => 'Schema table with primary and unique key.',
      'fields' => [
        'id' => [
          'type' => 'int',
          'default' => 0,
          'not null' => TRUE,
        ],
        'test_field' => [
          'type' => 'int',
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => ['test_field' => ['test_field']],
    ];

    $field_set_default_table_4_field_id_value_6_schema = [
      'description' => 'Schema table with primary and unique key.',
      'fields' => [
        'id' => [
          'type' => 'int',
          'default' => 6,
          'not null' => TRUE,
        ],
        'test_field' => [
          'type' => 'int',
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => ['test_field' => ['test_field']],
    ];

    $field_set_default_table_4_field_id_value_null_schema = [
      'description' => 'Schema table with primary and unique key.',
      'fields' => [
        'id' => [
          'type' => 'int',
          'default' => NULL,
          'not null' => TRUE,
        ],
        'test_field' => [
          'type' => 'int',
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => ['test_field' => ['test_field']],
    ];

    $field_set_default_table_5_field_test_field_string_value__schema = [
      'description' => 'Schema table with no indexes.',
      'fields' => [
        'id' => [
          'type' => 'serial',
        ],
        'test_field_string' => [
          'type' => 'varchar',
          'length' => 20,
          'not null' => TRUE,
          'default' => '',
        ],
      ],
    ];

    $field_set_default_table_5_field_test_field_string_value_my_new_default_value_schema = [
      'description' => 'Schema table with no indexes.',
      'fields' => [
        'id' => [
          'type' => 'serial',
        ],
        'test_field_string' => [
          'type' => 'varchar',
          'length' => 20,
          'not null' => TRUE,
          'default' => 'My new default value',
        ],
      ],
    ];

    $field_set_default_table_5_field_test_field_string_value_null_schema = [
      'description' => 'Schema table with no indexes.',
      'fields' => [
        'id' => [
          'type' => 'serial',
        ],
        'test_field_string' => [
          'type' => 'varchar',
          'length' => 20,
          'not null' => TRUE,
          'default' => NULL,
        ],
      ],
    ];

    return [
      [static::TEST_TABLE4, 'id', 0, $field_set_default_table_4_field_id_value_0_schema],
      [static::TEST_TABLE4, 'id', 6, $field_set_default_table_4_field_id_value_6_schema],
      [static::TEST_TABLE4, 'id', NULL, $field_set_default_table_4_field_id_value_null_schema],
      [static::TEST_TABLE5, 'test_field_string', '', $field_set_default_table_5_field_test_field_string_value__schema],
      [static::TEST_TABLE5, 'test_field_string', 'My new default value', $field_set_default_table_5_field_test_field_string_value_my_new_default_value_schema],
      [static::TEST_TABLE5, 'test_field_string', NULL, $field_set_default_table_5_field_test_field_string_value_null_schema],
    ];
  }

  /**
   * @covers ::fieldSetDefault
   * @dataProvider providerFieldSetDefault
   */
  public function testFieldSetDefault($table_data, $field_name, $field_default_value, $field_set_default_table_schema) {
    $this->assertFalse($this->schema->tableExists($table_data['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable($table_data['name'], $table_data['schema']);

    $this->assertTrue($this->schema->tableExists($table_data['name']), 'The table exists in the MongoDB database.');
    $this->checkTableSchema($table_data['name'], $table_data['schema']);

    $this->schema->fieldSetDefault($table_data['name'], $field_name, $field_default_value);

    $this->checkTableSchema($table_data['name'], $field_set_default_table_schema);
  }

  /**
   * @covers ::fieldSetDefault
   */
  public function testFieldSetDefaultForTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    // If we try to set the default value for a field on a table that does not
    // exist an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->fieldSetDefault(static::TEST_TABLE4['name'], 'id', 6);
  }

  /**
   * @covers ::fieldSetDefault
   */
  public function testFieldSetDefaultForFieldDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE4['name'], static::TEST_TABLE4['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE4['name']), 'The table exists in the MongoDB database.');

    // If we try to set the default value for a field on a table that does not
    // has such a field an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->fieldSetDefault(static::TEST_TABLE4['name'], 'does_not_exist_field', 6);
  }

  /**
   * Data provider for testFieldSetNoDefault().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the name of the field that has its default value removed.
   *     - the expected schema for the table after unsetting the default value
   *       for the field.
   */
  public static function providerFieldSetNoDefault() {
    $field_set_no_default_table_5_field_test_field_string_schema = [
      'description' => 'Schema table with no indexes.',
      'fields' => [
        'id' => [
          'type' => 'serial',
        ],
        'test_field_string' => [
          'type' => 'varchar',
          'length' => 20,
          'not null' => TRUE,
        ],
      ],
    ];

    $field_set_no_default_table_1_field_id_schema = [
      'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
      'fields' => [
        'id' => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'test_field' => [
          'type' => 'int',
          'not null' => TRUE,
          'description' => 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
        ],
        'test_field_string' => [
          'type' => 'varchar',
          'length' => 20,
          'not null' => TRUE,
          'default' => "'\"Quoting fun!'\"",
          'description' => 'Schema column description for string.',
        ],
        'test_field_string_ascii' => [
          'type' => 'varchar_ascii',
          'length' => 255,
          'description' => 'Schema column description for ASCII string.',
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => ['test_field' => ['test_field']],
    ];

    $field_set_no_default_table_1_field_test_field_string_schema = [
      'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
      'fields' => [
        'id' => [
          'type' => 'int',
          'default' => NULL,
          'not null' => TRUE,
        ],
        'test_field' => [
          'type' => 'int',
          'not null' => TRUE,
          'description' => 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
        ],
        'test_field_string' => [
          'type' => 'varchar',
          'length' => 20,
          'not null' => TRUE,
          'description' => 'Schema column description for string.',
        ],
        'test_field_string_ascii' => [
          'type' => 'varchar_ascii',
          'length' => 255,
          'description' => 'Schema column description for ASCII string.',
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => ['test_field' => ['test_field']],
    ];

    return [
      [static::TEST_TABLE1, 'id', $field_set_no_default_table_1_field_id_schema],
      [static::TEST_TABLE1, 'test_field_string', $field_set_no_default_table_1_field_test_field_string_schema],
      [static::TEST_TABLE5, 'test_field_string', $field_set_no_default_table_5_field_test_field_string_schema],
    ];
  }

  /**
   * @covers ::fieldSetNoDefault
   * @dataProvider providerFieldSetNoDefault
   */
  public function testFieldSetNoDefault($table_data, $field_name, $field_set_no_default_table_schema) {
    $this->assertFalse($this->schema->tableExists($table_data['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable($table_data['name'], $table_data['schema']);

    $this->assertTrue($this->schema->tableExists($table_data['name']), 'The table exists in the MongoDB database.');
    $this->checkTableSchema($table_data['name'], $table_data['schema']);

    $this->schema->fieldSetNoDefault($table_data['name'], $field_name);

    $this->checkTableSchema($table_data['name'], $field_set_no_default_table_schema);
  }

  /**
   * @covers ::fieldSetNoDefault
   */
  public function testFieldSetNoDefaultForTableDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');

    // If we try to unset the default value for a field on a table that does not
    // exist an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->fieldSetNoDefault(static::TEST_TABLE5['name'], 'test_field_string');
  }

  /**
   * @covers ::fieldSetNoDefault
   */
  public function testFieldSetNoDefaultForFieldDoesNotExist() {
    $this->assertFalse($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table does not exist in the MongoDB database.');

    $this->schema->createTable(static::TEST_TABLE5['name'], static::TEST_TABLE5['schema']);

    $this->assertTrue($this->schema->tableExists(static::TEST_TABLE5['name']), 'The table exists in the MongoDB database.');

    // If we try to un set the default value for a field on a table that does
    // not has such a field an exception should be thrown.
    $this->expectException('Drupal\Core\Database\SchemaObjectDoesNotExistException');
    $this->schema->fieldSetNoDefault(static::TEST_TABLE5['name'], 'does_not_exist_field');
  }

  /**
   * Data provider for testMongodbTableServiceFullPathAndBaseTable().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table data to use as base table (the name, the schema and the
   *       validation).
   *     - the array of table data to use as embedded tables (the name, the
   *       schema and the validation) keyed by their parent table name.
   *     - the expected full table path for each table keyed by their table name.
   */
  public static function providerMongodbTableServiceFullPathAndBaseTable() {
    return [
      [
        static::TEST_TABLE1,
        [
          static::TEST_TABLE1['name'] => [static::TEST_TABLE2],
          static::TEST_TABLE2['name'] => [static::TEST_TABLE3],
          static::TEST_TABLE3['name'] => [static::TEST_TABLE4],
          static::TEST_TABLE4['name'] => [static::TEST_TABLE5],
          static::TEST_TABLE5['name'] => [static::TEST_TABLE6],
        ],
        [
          'test_table1' => '',
          'test_table2' => 'test_table2.',
          'test_table3' => 'test_table2.test_table3.',
          'test_table4' => 'test_table2.test_table3.test_table4.',
          'test_table5' => 'test_table2.test_table3.test_table4.test_table5.',
          'test_table6' => 'test_table2.test_table3.test_table4.test_table5.test_table6.',
        ],
      ],
      [
        static::TEST_TABLE2,
        [
          static::TEST_TABLE2['name'] => [static::TEST_TABLE1],
          static::TEST_TABLE1['name'] => [static::TEST_TABLE3],
          static::TEST_TABLE3['name'] => [static::TEST_TABLE4, static::TEST_TABLE5, static::TEST_TABLE6],
        ],
        [
          'test_table2' => '',
          'test_table1' => 'test_table1.',
          'test_table3' => 'test_table1.test_table3.',
          'test_table4' => 'test_table1.test_table3.test_table4.',
          'test_table5' => 'test_table1.test_table3.test_table5.',
          'test_table6' => 'test_table1.test_table3.test_table6.',
        ],
      ],
    ];
  }

  /**
   * @covers Drupal\mongodb\Driver\Database\mongodb\TableInformation::getTableEmbeddedFullPath
   * @covers Drupal\mongodb\Driver\Database\mongodb\TableInformation::getTableBaseTable
   *
   * @dataProvider providerMongodbTableServiceFullPathAndBaseTable
   */
  public function testMongodbTableServiceFullPathAndBaseTable($base_table_data, $embedded_tables_data, $expected_full_paths) {
    // Create all the tables.
    $this->schema->createTable($base_table_data['name'], $base_table_data['schema']);
    foreach ($embedded_tables_data as $parent_table_name => $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $this->schema->createEmbeddedTable($parent_table_name, $embedded_table_data['name'], $embedded_table_data['schema']);
      }
    }

    // Test the MongoDB table service getTableBaseTable method.
    foreach ($expected_full_paths as $table => $expected_full_path) {
      $generated_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
      $this->assertEquals($generated_full_path, $expected_full_path, 'The generated full path for the table is as expected.');
    }

    // Test the MongoDB table service getTableBaseTable method.
    foreach ($embedded_tables_data as $embedded_table_array) {
      foreach ($embedded_table_array as $embedded_table_data) {
        $generated_base_table = $this->tableInformation->getTableBaseTable($embedded_table_data['name']);
        $this->assertEquals($generated_base_table, $base_table_data['name'], 'The generated base table for the table is as expected.');
      }
    }
  }

}
