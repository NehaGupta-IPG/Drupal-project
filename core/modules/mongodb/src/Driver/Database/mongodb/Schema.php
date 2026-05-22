<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Daffie\SqlLikeToRegularExpression;
use Drupal\Core\Database\Schema as DatabaseSchema;
use Drupal\Core\Database\SchemaException;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;
use Drupal\Core\Database\SchemaObjectExistsException;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Driver\Exception\ExecutionTimeoutException;
use MongoDB\Model\IndexInfo;

/**
 * The MongoDB implementation of \Drupal\Core\Database\Schema.
 */
class Schema extends DatabaseSchema {

  /**
   * Drop target table before renaming the table.
   *
   * @var bool
   */
  protected $dropTarget = TRUE;

  /**
   * The maximum allowed identifier length.
   *
   * The maximum allowed length for index, primary key and constraint names.
   * The length is calculated as follows:
   * "<database name>.<collection name>.<index name>".
   *
   * @var int
   *   The default value is 128.
   */
  protected $maxIdentifierLength = 127;

  /**
   * The MongoDB table information service.
   *
   * @var \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   */
  protected $tableInformation;

  /**
   * The create indexes on tables as a background process.
   *
   * @var bool
   */
  protected $createIndexInBackground = TRUE;

  /**
   * {@inheritdoc}
   */
  public function __construct($connection) {
    parent::__construct($connection);
    $this->tableInformation = $connection->tableInformation();
  }

  /**
   * {@inheritdoc}
   */
  public function createTable($name, $table) {
    if ($this->tableExists($name)) {
      throw new SchemaObjectExistsException("Table $name already exists.");
    }

    $table = TranslateSchema::createTable($name, $table);

    // Fields of type serial are automatically made not null.
    if (isset($table['primary key']) && is_array($table['primary key'])) {
      foreach ($table['primary key'] as $pkey_field) {
        if (isset($table['fields'][$pkey_field]['type']) && ($table['fields'][$pkey_field]['type'] != 'serial')) {
          if (!isset($table['fields'][$pkey_field]['not null']) || (isset($table['fields'][$pkey_field]['not null']) && !$table['fields'][$pkey_field]['not null'])) {
            throw new SchemaException("The '$pkey_field' field specification does not define 'not null' as TRUE.");
          }
        }
      }
    }

    // The table information needs to be saved first. It is used to generate the
    // table validation.
    $this->tableInformation->setTable($name, $table)->save();

    try {
      $prefixInfo = $this->getPrefixInfo($name);
      $result = $this->connection->getConnection()->createCollection(
        $prefixInfo['table'],
        [
          'validator' => $this->getTableValidation($name),
          'session' => $this->connection->getMongodbSession(),
        ],
      );
    }
    catch (CommandException $e) {
      // The function tableExists() does not work in multi-document
      // transactions. The table creation will fail with a CommandException.
      // Catch this exception an throw a SchemaObjectExistsException instead.
      if ($e->getCode() == 48) {
        throw new SchemaObjectExistsException("Table $name already exists.");
      }

      throw $e;
    }

    // Create the new keys and/or indexes.
    $this->createKeys($name, $table);

    return $result && $result->ok;
  }

  /**
   * Create a new embedded table from a Drupal table definition.
   *
   * @param string $parent_table_name
   *   The name of the table to create the embedded table on.
   * @param string $embedded_table_name
   *   The name of the embedded table to create.
   * @param array $embedded_table_schema
   *   A Schema API table definition array.
   *
   * @throws \Drupal\Core\Database\SchemaObjectDoesNotExistException
   *   If the specified base table doesn't exist.
   * @throws \Drupal\Core\Database\SchemaObjectExistsException
   *   If the specified embedded table exists as a base/embedded table or as an
   *   embedded table on another base table.
   */
  public function createEmbeddedTable($parent_table_name, $embedded_table_name, $embedded_table_schema) {
    if (!$this->tableExists($parent_table_name)) {
      throw new SchemaObjectDoesNotExistException("Cannot add embedded table $embedded_table_name to the $parent_table_name, because $parent_table_name doesn't exist.");
    }
    if ($this->tableExists($embedded_table_name)) {
      throw new SchemaObjectExistsException("The embedded table $embedded_table_name could not be created, because the $embedded_table_name already exists.");
    }

    $base_table_name = $this->tableInformation->getTableBaseTable($parent_table_name);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("The embedded table $embedded_table_name could not be created, because the base table doesn't exists.");
    }

    // Save the embedded table to the MongoDB table information.
    $this->tableInformation->setEmbeddedTable($embedded_table_name, $embedded_table_schema, $parent_table_name)->save();

    $prefixInfo = $this->getPrefixInfo($base_table_name);
    $cursor = $this->connection->getConnection()->command(
      [
        'collMod' => $prefixInfo['table'],
        'validator' => $this->getTableValidation($base_table_name),
      ],
      [
        'session' => $this->connection->getMongodbSession(),
      ],
    );

    // Create the new keys and/or indexes. You have to be careful to create not
    // to many indexes. The maximum that MongoDB support on a single table is
    // 64.
    // See: https://docs.mongodb.com/manual/reference/limits/#Number-of-Indexes-per-Collection
    $this->createKeys($embedded_table_name, $embedded_table_schema);

    $result = current($cursor->toArray());

    return $result && $result->ok;
  }

  /**
   * Get the MongoDB table validation for the fields description.
   *
   * @param string $table
   *   A table name for which to construct the MongoDB validation.
   *
   * @return array
   *   The validator for the MongoDB createCollection() function.
   */
  public function getTableValidation($table) {
    $table_schema = $this->tableInformation->getTable($table);
    $validation = $this->generateTableValidation($table_schema);
    $validation = array_merge($validation, $this->generateEmbeddedTablesValidation($table));

    return !empty($validation) ? ['$and' => $validation] : [];
  }

  /**
   * Helper method for getTableValidation().
   *
   * Get the MongoDB table validation for embedded tables.
   *
   * @param string $table_name
   *   A table name for which to construct the MongoDB validation.
   *
   * @return array
   *   The validator for the MongoDB createCollection() function. Still
   *   needs to be wrapped in an "$and" array.
   */
  protected function generateEmbeddedTablesValidation(string $table_name): array {
    $validation = [];
    $embedded_tables = $this->tableInformation->getTableEmbeddedTables($table_name);
    // Sort the embedded table to get a predictable validation array.
    sort($embedded_tables);
    foreach ($embedded_tables as $embedded_table_name) {
      // For the validation to work in MongoDB the embedded table fields need to
      // be linked to their embedded table. For embedded table created on an
      // other embedded table need to use the full list of parent embedded
      // tables to make their validation work. Parent embedded tables are
      // separated by dots. See:
      // https://docs.mongodb.com/manual/core/document/#document-dot-notation.
      $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($embedded_table_name);
      $embedded_table_schema = $this->tableInformation->getTable($embedded_table_name);
      $validation = array_merge($validation, $this->generateTableValidation($embedded_table_schema, $embedded_full_path));
      $validation = array_merge($validation, $this->generateEmbeddedTablesValidation($embedded_table_name));
    }

    return $validation;
  }

  /**
   * Helper method for getTableValidation().
   *
   * Generates the MongoDB table validation for the given schema and full path
   * data.
   *
   * @param array $schema
   *   A field description array or a table description, as specified in the
   *   schema documentation.
   * @param string $embedded_full_path
   *   Set if table is an embedded table. Do not set for the base table.
   *
   * @return array
   *   The base validator for the MongoDB createCollection() function. Still
   *   needs to be wrapped in an "$and" array.
   */
  protected function generateTableValidation($schema, $embedded_full_path = '') {
    $validation = [];
    $map = $this->getFieldTypeMap();
    if (isset($schema['fields'])) {
      $fields = $schema['fields'];
      foreach ($fields as $field_name => $field) {
        // Embedded tables need to add their table name to the field name.
        if (!empty($embedded_full_path)) {
          $field_name = $embedded_full_path . $field_name;
        }

        if (!isset($field['size'])) {
          $field['size'] = 'normal';
        }

        if (isset($field['type']) || isset($field['mongodb_type'])) {
          // Serial field types are always not null, unsigned and
          // auto-incremented.
          if (isset($field['type']) && ($field['type'] == 'serial')) {
            $field['not null'] = TRUE;
            $field['unsigned'] = TRUE;
            $field['auto_increment'] = TRUE;
          }

          // Get the MongoDB type or get the database type from
          // getFieldTypeMap().
          $type = $field['mongodb_type'] ?? $map[$field['type'] . ':' . $field['size']];

          // MongoDB 64 bit-integer values are only stored as such when their
          // value is more then the 32-bit integer can store. Else they are
          // stored as 32-bit integer type. Therefore the validation must allow
          // both 32-bit and 64-bit integer values for big integer storage.
          if ($type == 'long') {
            $type = ['int', 'long'];
          }

          // MongoDB date fields are always signed. Negative values represent
          // dates before 1970.
          if (($type == 'date') && isset($field['unsigned'])) {
            $field['unsigned'] = FALSE;
          }
          if (isset($field['not null']) && $field['not null']) {
            $validation[] = [$field_name => ['$type' => $type]];
            $validation[] = [$field_name => ['$exists' => TRUE]];

            if (isset($field['unsigned']) && $field['unsigned']) {
              $validation[] = [$field_name => ['$gte' => 0]];
            }
          }
          else {
            if (isset($field['unsigned']) && $field['unsigned']) {
              $validation[] = [
                '$or' => [
                  [
                    '$and' => [
                      [$field_name => ['$type' => $type]],
                      [$field_name => ['$gte' => 0]],
                    ],
                  ],
                  [$field_name => ['$exists' => FALSE]],
                ],
              ];
            }
            else {
              $validation[] = [
                '$or' => [
                  [$field_name => ['$type' => $type]],
                  [$field_name => ['$exists' => FALSE]],
                ],
              ];
            }
          }
        }
        else {
          throw new MongodbSQLException('The field type must be set. MongoDB needs this for the table validation.');
        }
      }

      // Embedded table must also be able to be empty.
      if (!empty($embedded_full_path)) {
        $empty_embedded_table_validation = [];
        foreach ($fields as $field_name => $field) {
          $field_name = $embedded_full_path . $field_name;
          $empty_embedded_table_validation[] = [$field_name => ['$exists' => FALSE]];
        }

        if (!empty($empty_embedded_table_validation)) {
          return [
            [
              '$or' => [
                ['$and' => $empty_embedded_table_validation],
                ['$and' => $validation],
              ],
            ],
          ];
        }
      }
    }

    return $validation;
  }

  /**
   * Get the field type map.
   *
   * This maps a generic data type in combination with its data size to the
   * engine-specific data type. For more information about available types in
   * MongoDB: https://docs.mongodb.com/manual/reference/operator/query/type/.
   */
  public function getFieldTypeMap() {
    // Put :normal last so it gets preserved by array_flip. This makes
    // it much easier for modules (such as schema.module) to map
    // database types back into schema types.
    // $map does not use drupal_static as its value never changes.
    static $map = [
      'varchar_ascii:normal' => 'string',
      'varchar:normal' => 'string',
      'char:normal' => 'string',

      'text:tiny' => 'string',
      'text:small' => 'string',
      'text:medium' => 'string',
      'text:normal' => 'string',
      'text:big' => 'string',

      // The MongoDB validation for long fails when you try to insert an int.
      // Mongodb PHP extension >= 1.5.0 is needed for MongoDB\BSON\Int64.
      'int:tiny' => 'int',
      'int:small' => 'int',
      'int:medium' => 'int',
      'int:normal' => 'int',
      'int:big' => 'long',

      'serial:tiny' => 'int',
      'serial:small' => 'int',
      'serial:medium' => 'int',
      'serial:normal' => 'int',
      'serial:big' => 'long',

      // MongoDB needs a date field to be of type date. Instead of type int.
      'date:tiny' => 'date',
      'date:small' => 'date',
      'date:medium' => 'date',
      'date:normal' => 'date',
      'date:big' => 'date',

      'float:tiny' => 'double',
      'float:small' => 'double',
      'float:medium' => 'double',
      'float:normal' => 'double',
      'float:big' => 'double',

      // MongoDB needs a numeric field to be of type decimal.
      'numeric:tiny' => 'decimal',
      'numeric:small' => 'decimal',
      'numeric:medium' => 'decimal',
      'numeric:normal' => 'decimal',
      'numeric:big' => 'decimal',

      'blob:normal' => 'binData',
      'blob:big' => 'binData',

      'bool:tiny' => 'bool',
      'bool:small' => 'bool',
      'bool:medium' => 'bool',
      'bool:normal' => 'bool',
      'bool:big' => 'bool',
    ];

    return $map;
  }

  /**
   * {@inheritdoc}
   */
  public function tableExists($table, $add_prefix = TRUE) {
    try {
      $prefixed_table = $this->connection->getPrefix() . $table;
      $options = [
        'authorizedCollections' => TRUE,
        'maxTimeMS' => 1000,
        'filter' => [
          'name' => $prefixed_table,
        ],
      ];
      // The listing of tables in a multi-document transaction is not
      // supported by MongoDB.
      // @todo Fix this bug!
      if (!$this->connection->getMongodbSession()->isInTransaction()) {
        $options['session'] = $this->connection->getMongodbSession();
      }
      $collections = $this->connection->getConnection()->listCollectionNames($options);
      foreach ($collections as $collection) {
        if ($collection === $prefixed_table) {
          return TRUE;
        }
      }
    }
    // phpcs:ignore SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch.NonCapturingCatchRequired
    catch (ExecutionTimeoutException) {
      // Running Functional tests gets timeout. One fatal error we got was:
      // "Maximum execution time of 60 seconds exceeded in
      // vendor/mongodb/mongodb/src/Model/CollectionInfo.php
      // on line 86".
      $base_tables = $this->tableInformation->getAllBaseTables();
      if (in_array($table, $base_tables)) {
        return TRUE;
      }
    }

    // Test for an embedded table.
    $base_table = $this->tableInformation->getTableBaseTable($table);
    $embedded_to_table = $this->tableInformation->getTableEmbeddedToTable($table);

    // If the base table or the embedded table do not exist, reload the table
    // information and try again.
    if (empty($embedded_to_table) || empty($base_table)) {
      $this->tableInformation->load(TRUE);
      $base_table = $this->tableInformation->getTableBaseTable($table);
      $embedded_to_table = $this->tableInformation->getTableEmbeddedToTable($table);
    }

    if (!empty($embedded_to_table) && !empty($base_table) && ($table != $base_table) && $this->tableExists($base_table)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function findTables($table_expression) {
    $prefix_length = strlen($this->connection->getPrefix());
    $tables = [];

    // Add the table prefix to the expression.
    $table_expression = $this->connection->getPrefix() . $table_expression;
    $pattern = SqlLikeToRegularExpression::convert($table_expression);
    $collections = $this->connection->getConnection()->listCollectionNames([
      'authorizedCollections' => TRUE,
      'maxTimeMS' => 1000,
      'filter' => [
        'name' => new Regex($pattern, 'i'),
      ],
      'session' => $this->connection->getMongodbSession(),
    ]);
    foreach ($collections as $collection) {
      // Remove the table prefix.
      $collection = substr($collection, $prefix_length);
      $tables[$collection] = $collection;
    }

    // Hack to set the table_information table as the last table. Therefore in
    // testing will the table be dropped as the last table.
    if (in_array(TableInformation::TABLE_NAME, $tables, TRUE)) {
      unset($tables[TableInformation::TABLE_NAME]);
      $tables[TableInformation::TABLE_NAME] = TableInformation::TABLE_NAME;
    }

    return array_values($tables);
  }

  /**
   * {@inheritdoc}
   */
  public function renameTable($table, $new_name) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot rename $table to $new_name: table $table doesn't exist.");
    }
    if ($this->tableExists($new_name)) {
      throw new SchemaObjectExistsException("Cannot rename $table to $new_name: table $new_name already exists.");
    }

    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("The embedded table $table could not be created, because the base table doesn't exists.");
    }

    $prefixInfo = $this->getPrefixInfo($base_table_name);
    if ($base_table_name == $table) {
      $this->connection->getConnection()->renameCollection(
        $prefixInfo['table'],
        $prefixInfo['prefix'] . $new_name,
        NULL,
        [
          'dropTarget' => $this->dropTarget,
          'session' => $this->connection->getMongodbSession(),
        ],
      );

      $base_table_schema = $this->tableInformation->getTable($table);
      $this->tableInformation
        ->setTable($new_name, $base_table_schema)
        ->setTable($table);
    }
    else {
      $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
      // Remove the old table name from the full path.
      $embedded_full_path = preg_replace('/' . $table . '\.$/i', '', $embedded_full_path);

      $this->connection->getConnection()->selectCollection($prefixInfo['table'])->updateMany(
        [],
        ['$rename' => [$embedded_full_path . $table => $embedded_full_path . $new_name]],
        ['session' => $this->connection->getMongodbSession()],
      );
    }

    // Link all embedded tables to the new table name.
    $embedded_tables = $this->tableInformation->getTableEmbeddedTables($table);
    foreach ($embedded_tables as $embedded_table) {
      $this->tableInformation->setTableEmbeddedToTable($embedded_table, $new_name);
    }

    if ($base_table_name == $table) {
      $this->tableInformation->save();
    }
    else {
      $parent_table_name = $this->tableInformation->getTableEmbeddedToTable($table);
      if ($parent_table_name) {
        $this->tableInformation
          ->setEmbeddedTable($new_name, $this->tableInformation->getEmbeddedTable($table), $parent_table_name)
          ->setEmbeddedTable($table, '', '')
          ->save();
      }

      // Delete all indexes for the embedded table and recreate them. The index
      // names and keys have their old embedded table name in them.
      $indexes = $this->getTableIndexesFromDatabase($base_table_name);
      foreach ($indexes as $index) {
        // The embedded table name can be at the beginning of the string and be
        // separated by a dot from the rest of the string. Or the embedded table
        // name can be somewhere in the middle of the string and be separated
        // from the rest of the string by leading dot and trailing dot.
        $is_first_table = (substr($index->getName(), 0, strlen($table . '.')) == $table . '.') ? TRUE : FALSE;
        if ($is_first_table || (strpos($index->getName(), '.' . $table . '.') !== FALSE)) {
          // Delete the old index.
          $this->connection->getConnection()->selectCollection($prefixInfo['table'])->dropIndex(
            $index->getName(),
            [
              'session' => $this->connection->getMongodbSession(),
            ],
          );

          // Create the new index.
          $key = [];
          foreach ($index->getKey() as $index_key => $index_value) {
            if ($is_first_table) {
              $key[str_replace($table . '.', $new_name . '.', $index_key)] = $index_value;
            }
            else {
              $key[str_replace('.' . $table . '.', '.' . $new_name . '.', $index_key)] = $index_value;
            }
          }
          if ($is_first_table) {
            $name = str_replace($table . '.', $new_name . '.', $index->getName());
          }
          else {
            $name = str_replace('.' . $table . '.', '.' . $new_name . '.', $index->getName());
          }
          $this->connection->getConnection()->selectCollection($prefixInfo['table'])->createIndex(
            $key,
            [
              'name' => $name,
              'unique' => $index->isUnique(),
              'background' => $this->createIndexInBackground,
              'session' => $this->connection->getMongodbSession(),
            ],
          );
        }
      }

      // Update the base table validation.
      $this->connection->getConnection()->command(
        [
          'collMod' => $prefixInfo['table'],
          'validator' => $this->getTableValidation($base_table_name),
        ],
        [
          'session' => $this->connection->getMongodbSession(),
        ],
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function dropTable($table) {
    if (!$this->tableExists($table)) {
      return FALSE;
    }

    // If we are going to delete the table from the table information service,
    // then we should not update the table information service.
    if ($table == TableInformation::TABLE_NAME) {
      $prefixInfo = $this->getPrefixInfo($table);
      // The dropping of a table in a multi-document transaction is not
      // supported by MongoDB.
      // @todo Fix this bug!
      if ($this->connection->getMongodbSession()->isInTransaction()) {
        $result = $this->connection->getConnection()->dropCollection($prefixInfo['table']);
      }
      else {
        $result = $this->connection->getConnection()->dropCollection($prefixInfo['table'], ['session' => $this->connection->getMongodbSession()]);
      }

      return $result && $result->ok;
    }

    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("The table $table could not be dropped, because the base table doesn't exists.");
    }

    // Drop all tables that are an embedded table to the base table.
    $embedded_tables = $this->tableInformation->getTableEmbeddedTables($table);
    foreach ($embedded_tables as $embedded_table) {
      $this->dropTable($embedded_table);
    }

    // Remove the table information for the table.
    $this->tableInformation->setTable($table)->save();

    $prefixInfo = $this->getPrefixInfo($base_table_name);
    if ($table == $base_table_name) {
      // The dropping of a table in a multi-document transaction is not
      // supported by MongoDB.
      // @todo Fix this bug!
      if ($this->connection->getMongodbSession()->isInTransaction()) {
        $result = $this->connection->getConnection()->dropCollection($prefixInfo['table']);
      }
      else {
        $result = $this->connection->getConnection()->dropCollection($prefixInfo['table'], ['session' => $this->connection->getMongodbSession()]);
      }
    }
    else {
      // Delete all indexes for the embedded table.
      $indexes = $this->getTableIndexesFromDatabase($base_table_name);
      foreach ($indexes as $index) {
        // The embedded table name can be at the beginning of the string and be
        // separated by a dot from the rest of the string. Or the embedded table
        // name can be somewhere in the middle of the string and be separated
        // from the rest of the string by leading dot and trailing dot.
        if ((substr($index->getName(), 0, strlen($table . '.')) == $table . '.') || (strpos($index->getName(), '.' . $table . '.') !== FALSE)) {
          // Delete the old index.
          $this->connection->getConnection()->selectCollection($prefixInfo['table'])->dropIndex(
            $index->getName(),
            [
              'session' => $this->connection->getMongodbSession(),
            ],
          );
        }
      }

      // Update the base table validation.
      $cursor = $this->connection->getConnection()->command(
        [
          'collMod' => $prefixInfo['table'],
          'validator' => $this->getTableValidation($base_table_name),
        ],
        [
          'session' => $this->connection->getMongodbSession(),
        ],
      );
      $result = current($cursor->toArray());
    }

    return $result && $result->ok;
  }

  /**
   * Get the table schema from the table information service.
   *
   * @param string $table
   *   The name of the table.
   *
   * @return array
   *   The table schema.
   */
  public function getTableSchema($table) {
    return $this->tableInformation->getTable($table);
  }

  /**
   * Get the MongoDB table validation from the database.
   *
   * @param string $table
   *   The name of the table.
   *
   * @return array
   *   List of validators for the collection or an empty array.
   */
  public function getTableValidationFromDatabase($table) {
    $prefixInfo = $this->getPrefixInfo($table);
    foreach ($this->connection->getConnection()->listCollections([
      'authorizedCollections' => TRUE,
      'maxTimeMS' => 1000,
      'session' => $this->connection->getMongodbSession(),
    ]) as $collectionInfo) {
      if ($collectionInfo->getName() == $prefixInfo['table']) {
        $collectionOptions = $collectionInfo->getOptions();
        if (isset($collectionOptions['validator'])) {
          return $collectionOptions['validator'];
        }
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function fieldExists($table, $column) {
    if ($this->tableInformation->getTableField($table, $column)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addField($table, $field, $spec, $keys_new = []) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot add field $table.$field: table doesn't exist.");
    }
    if ($this->fieldExists($table, $field)) {
      throw new SchemaObjectExistsException("Cannot add field $table.$field: field already exists.");
    }

    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("The table $table could not be dropped, because the base table doesn't exists.");
    }

    if (isset($keys_new['primary key']) && is_array($keys_new['primary key'])) {
      $not_null_fields = $this->tableInformation->getTableFieldsWithNotNull($table);
      if (isset($spec['not null']) && $spec['not null']) {
        $not_null_fields[] = $field;
      }
      foreach ($keys_new['primary key'] as $pkey_field) {
        if (!in_array($pkey_field, $not_null_fields)) {
          throw new SchemaException("The '$pkey_field' field specification does not define 'not null' as TRUE.");
        }
      }
    }

    // Save to the table information service.
    $this->tableInformation->setTableField($table, $field, $spec);
    foreach ($keys_new as $key_type => $keys) {
      if (mb_strtolower($key_type) == 'primary key') {
        $this->tableInformation->setTablePrimaryKey($table, $keys);
      }
      elseif (mb_strtolower($key_type) == 'unique keys') {
        foreach ($keys as $name => $values) {
          $this->tableInformation->setTableUniqueKey($table, $name, $values);
        }
      }
      elseif (mb_strtolower($key_type) == 'indexes') {
        foreach ($keys as $name => $values) {
          $this->tableInformation->setTableIndex($table, $name, $values);
        }
      }
    }
    // The table information needs to be saved first. It is used to generate the
    // table validation.
    $this->tableInformation->save();

    $prefixInfo = $this->getPrefixInfo($base_table_name);
    $cursor = $this->connection->getConnection()->command(
      [
        'collMod' => $prefixInfo['table'],
        'validator' => $this->getTableValidation($base_table_name),
      ],
      [
        'session' => $this->connection->getMongodbSession(),
      ],
    );

    // The setting of an initial value for all existing field works only for
    // non-embedded tables.
    if (isset($spec['initial']) && ($table == $base_table_name)) {
      $initial = $spec['initial'];
      if (in_array($spec['type'], ['int', 'serial'])) {
        $initial = (int) $initial;
      }
      elseif (in_array($spec['type'], ['text', 'char', 'varchar', 'varchar_ascii'])) {
        $initial = (string) $initial;
      }
      elseif ($spec['type'] == 'float') {
        $initial = (float) $initial;
      }
      elseif ($spec['type'] == 'numeric') {
        $precision = $spec['precision'] ?? 0;
        $scale = $spec['scale'] ?? 0;
        $decimals = ($precision > $scale) ? $precision - $scale : 0;
        $initial = number_format((float) $initial, $decimals, '.', '');
        $initial = new Decimal128($initial);
      }
      elseif ($spec['type'] == 'date') {
        $initial = new UTCDateTime($initial);
      }

      $this->connection->update($table)
        ->fields([$field => $initial])
        ->execute();
    }

    // The setting of an initial value for all existing fields works only for
    // non-embedded tables.
    if (isset($spec['initial_from_field']) && ($table == $base_table_name)) {
      $initial_from_field = $spec['initial_from_field'];
      $all_rows = $this->connection->select($table)
        ->fields($table, ['_id', $initial_from_field])
        ->execute()
        ->fetchAll();

      foreach ($all_rows as $row) {
        $_id = new ObjectID($row->{'_id'});
        if (isset($row->{$initial_from_field})) {
          $value = $row->{$initial_from_field};
          if (in_array($spec['type'], ['int', 'serial'])) {
            $value = (int) $value;
          }
          elseif (in_array($spec['type'], ['text', 'char', 'varchar', 'varchar_ascii'])) {
            $value = (string) $value;
          }
          elseif ($spec['type'] == 'float') {
            $value = (float) $value;
          }
          elseif ($spec['type'] == 'numeric') {
            $precision = $spec['precision'] ?? 0;
            $scale = $spec['scale'] ?? 0;
            $decimals = ($precision > $scale) ? $precision - $scale : 0;
            $value = number_format((float) $value, $decimals, '.', '');
            $value = new Decimal128($value);
          }
          elseif ($spec['type'] == 'date') {
            $value = new UTCDateTime($value);
          }

          $this->connection->update($table)
            ->fields([$field => $value])
            ->condition('_id', $_id)
            ->execute();
        }
      }
    }

    // There can be only one primary key.
    if (isset($keys_new['primary key']) && $this->primaryKeyExists($table)) {
      $this->dropPrimaryKey($table);
    }

    // Create the new keys and/or indexes.
    $this->createKeys($table, $keys_new);

    $result = current($cursor->toArray());

    return $result && $result->ok;
  }

  /**
   * {@inheritdoc}
   */
  public function dropField($table, $field) {
    if (!$this->fieldExists($table, $field)) {
      return FALSE;
    }

    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("The table $table could not be dropped, because the base table doesn't exists.");
    }

    // The table information needs to be saved first. It is used to generate the
    // table validation.
    $this->tableInformation->setTableField($table, $field, '')->save();

    // Remove keys and /or indexes if field is part of them.
    $table_schema = $this->tableInformation->getTable($table);
    if (isset($table_schema['primary key']) && is_array($table_schema['primary key'])) {
      if (in_array($field, $table_schema['primary key'], TRUE)) {
        $this->tableInformation->setTablePrimaryKey($table)->save();
        $this->dropPrimaryKey($table);
      }
    }
    if (isset($table_schema['unique keys']) && is_array($table_schema['unique keys'])) {
      foreach ($table_schema['unique keys'] as $unique_key_name => $unique_key_fields) {
        if (in_array($field, $unique_key_fields, TRUE)) {
          $this->tableInformation->setTableUniqueKey($table, $unique_key_name)->save();
          $this->dropUniqueKey($table, $unique_key_name);
        }
      }
    }
    if (isset($table_schema['indexes']) && is_array($table_schema['indexes'])) {
      foreach ($table_schema['indexes'] as $index_name => $index_fields) {
        if (in_array($field, $index_fields, TRUE)) {
          $this->tableInformation->setTableIndex($table, $index_name)->save();
          $this->dropIndex($table, $index_name);
        }
      }
    }

    $prefixInfo = $this->getPrefixInfo($base_table_name);
    $cursor = $this->connection->getConnection()->command(
      [
        'collMod' => $prefixInfo['table'],
        'validator' => $this->getTableValidation($base_table_name),
      ],
      [
        'session' => $this->connection->getMongodbSession(),
      ],
    );

    $result = current($cursor->toArray());

    return $result && $result->ok;
  }

  /**
   * {@inheritdoc}
   */
  public function changeField($table, $field, $field_new, $spec, $keys_new = []) {
    if (!$this->fieldExists($table, $field)) {
      throw new SchemaObjectDoesNotExistException("Cannot change the definition of field $table.$field: field doesn't exist.");
    }
    if (($field != $field_new) && $this->fieldExists($table, $field_new)) {
      throw new SchemaObjectExistsException("Cannot rename field $table.$field to $field_new: target field already exists.");
    }

    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("The table $table could not be dropped, because the base table doesn't exists.");
    }

    if (isset($keys_new['primary key']) && is_array($keys_new['primary key'])) {
      $not_null_fields = $this->tableInformation->getTableFieldsWithNotNull($table);
      if (isset($spec['not null']) && $spec['not null']) {
        $not_null_fields[] = $field_new;
      }
      foreach ($keys_new['primary key'] as $pkey_field) {
        if (!in_array($pkey_field, $not_null_fields)) {
          throw new SchemaException("The '$pkey_field' field specification does not define 'not null' as TRUE.");
        }
      }
    }

    $original_field_spec = $this->tableInformation->getTableField($table, $field);
    $original_primary_key_fields = NULL;

    // Remove the field and keys and /or indexes if field is part of them.
    $this->tableInformation->setTableField($table, $field, '');
    $table_schema = $this->tableInformation->getTable($table);
    if (isset($table_schema['primary key']) && is_array($table_schema['primary key'])) {
      if (in_array($field, $table_schema['primary key'], TRUE)) {
        $this->tableInformation->setTablePrimaryKey($table);
        $this->dropPrimaryKey($table);
        // Save the original primary key fields.
        $original_primary_key_fields = $table_schema['primary key'];
      }
    }
    if (isset($table_schema['unique keys']) && is_array($table_schema['unique keys'])) {
      foreach ($table_schema['unique keys'] as $unique_key_name => $unique_key_fields) {
        if (in_array($field, $unique_key_fields, TRUE)) {
          $this->tableInformation->setTableUniqueKey($table, $unique_key_name);
          $this->dropUniqueKey($table, $unique_key_name);
        }
      }
    }
    if (isset($table_schema['indexes']) && is_array($table_schema['indexes'])) {
      foreach ($table_schema['indexes'] as $index_name => $index_fields) {
        if (in_array($field, $index_fields, TRUE)) {
          $this->tableInformation->setTableIndex($table, $index_name);
          $this->dropIndex($table, $index_name);
        }
      }
    }

    if (isset($spec['type']) && ($spec['type'] == 'serial')) {
      $max = $this->connection->select($table)
        ->fields($table, [$field])
        ->orderBy($field, 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();

      // Save the maximum used value as the current id in the sequences storage.
      $this->connection->sequences()->setId($table, $max);
    }

    // Save to the table information service.
    $this->tableInformation->setTableField($table, $field_new, $spec);
    foreach ($keys_new as $key_type => $keys) {
      if (mb_strtolower($key_type) == 'primary key') {
        // There can be only one primary key. We are going to add one. If there
        // is an existing one that one must be dropped.
        if ($this->primaryKeyExists($table)) {
          $this->dropPrimaryKey($table);
        }
        $this->tableInformation->setTablePrimaryKey($table, $keys);
      }
      elseif (mb_strtolower($key_type) == 'unique keys') {
        foreach ($keys as $name => $values) {
          $this->tableInformation->setTableUniqueKey($table, $name, $values);
        }
      }
      elseif (mb_strtolower($key_type) == 'indexes') {
        foreach ($keys as $name => $values) {
          $this->tableInformation->setTableIndex($table, $name, $values);
        }
      }
    }
    // The table information needs to be saved first. It is used to generate the
    // table validation.
    $this->tableInformation->save();

    $prefixInfo = $this->getPrefixInfo($base_table_name);
    $cursor = $this->connection->getConnection()->command(
      [
        'collMod' => $prefixInfo['table'],
        'validator' => $this->getTableValidation($base_table_name),
      ],
      [
        'session' => $this->connection->getMongodbSession(),
      ],
    );
    $result = current($cursor->toArray());

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
    if (($field != $field_new) || (!empty($original_field_spec['type']) && !empty($spec['type']) && ($original_field_spec['type'] != $spec['type']))) {
      $cursor = $this->connection->getConnection()->selectCollection($prefixInfo['table'])->find(
        [
          $embedded_full_path . $field => ['$exists' => TRUE],
        ],
        [
          'projection' => [$embedded_full_path . $field => 1, '_id' => 1],
          'session' => $this->connection->getMongodbSession(),
        ]
      );
      foreach ($cursor as $row) {
        if (!empty($original_field_spec['type']) && !empty($spec['type']) && ($original_field_spec['type'] != $spec['type'])) {
          if (in_array($spec['type'], ['varchar_ascii', 'varchar', 'char', 'text'])) {
            $row->{$embedded_full_path . $field} = (string) $row->{$embedded_full_path . $field};
          }
          elseif (in_array($spec['type'], ['int', 'serial'])) {
            $row->{$embedded_full_path . $field} = (int) $row->{$embedded_full_path . $field};
          }
          // Maybe add more typecasting.
        }
        $updates = [];
        $updates['$set'] = [$embedded_full_path . $field_new => $row->{$embedded_full_path . $field}];
        if ($field != $field_new) {
          $updates['$unset'] = [$embedded_full_path . $field => ''];
        }

        $this->connection->getConnection()->selectCollection($prefixInfo['table'])->updateOne(
          [
            '_id' => $row->_id,
          ],
          $updates,
          [
            'session' => $this->connection->getMongodbSession(),
          ],
        );
      }
    }

    // Create the new keys and/or indexes.
    $this->createKeys($table, $keys_new);

    // @todo Maybe we need to do the same for the other indexes.
    if ($original_primary_key_fields) {
      $new_primary_key_fields = [];
      $new_primary_key_fields_with_full_path = [];
      foreach ($original_primary_key_fields as $original_primary_key_field) {
        if ($original_primary_key_field == $field) {
          $new_primary_key_fields[] = $field_new;
          $new_primary_key_fields_with_full_path[] = $embedded_full_path . $field_new;
        }
        else {
          $new_primary_key_fields[] = $original_primary_key_field;
          $new_primary_key_fields_with_full_path[] = $embedded_full_path . $original_primary_key_field;
        }
      }

      // Check if there is already an index on the fields for which to create
      // the new primary key.
      if ($existing_index = $this->getIndexName($base_table_name, $new_primary_key_fields_with_full_path)) {
        $existing_index = substr($existing_index, strlen($embedded_full_path));

        if (substr($existing_index, -6) == '__pkey') {
          $this->dropPrimaryKey($table);
        }
        elseif (substr($existing_index, -5) == '__key') {
          $this->dropUniqueKey($table, substr($existing_index, 0, -5));
        }
        elseif (substr($existing_index, -5) == '__idx') {
          $this->dropIndex($table, substr($existing_index, 0, -5));
        }
      }

      $this->addPrimaryKey($table, $new_primary_key_fields);
    }

    return $result && $result->ok;
  }

  /**
   * Get the MongoDB table validation from the database.
   *
   * @param string $table
   *   The name of the table.
   *
   * @return array
   *   List of indexes for the collection or an empty array. The indexes are of
   *   the type MongoDB\Model\IndexInfo.
   */
  public function getTableIndexesFromDatabase($table) {
    $prefixInfo = $this->getPrefixInfo($table);
    $indexes = [];
    foreach ($this->connection->getConnection()->selectCollection($prefixInfo['table'])->listIndexes(['session' => $this->connection->getMongodbSession()]) as $indexInfo) {
      $indexes[] = $indexInfo;
    }
    return $indexes;
  }

  /**
   * Get the MongoDB index name.
   *
   * @param string $table
   *   The name of the table.
   * @param array $fields
   *   The array with field names to search the index for. If a field name is an
   *   array the first element is the field name and the second one is 'ASC' or
   *   'DESC'.
   *
   * @return string|null
   *   List of indexes for the collection or an empty array. The indexes are of
   *   the type MongoDB\Model\IndexInfo.
   */
  protected function getIndexName($table, $fields) {
    $fields = $this->createKeyArray($fields);
    $indexes = $this->getTableIndexesFromDatabase($table);
    foreach ($indexes as $indexInfo) {
      if ($indexInfo->getKey() === $fields) {
        return $indexInfo->getName();
      }
    }

    return NULL;
  }

  /**
   * Get the MongoDB table validation from the database.
   *
   * @param string $table
   *   The name of the table.
   * @param string $name
   *   The index name.
   *
   * @return bool|\MongoDB\Model\IndexInfo
   *   The indexes are of the type MongoDB\Model\IndexInfo.
   */
  public function getTableIndexFromDatabase($table, $name) {
    // ::ensureIdentifiersLength() expects three parameters. Thus we split our
    // constraint name in a proper name and a type.
    if ($name == 'pkey') {
      $type = $name;
      $name = '';
    }
    else {
      $pos = strrpos($name, '__');
      $type = substr($name, $pos + 2);
      $name = substr($name, 0, $pos);
    }
    $index_name = $this->ensureIdentifiersLength($table, $name, $type);
    $prefixInfo = $this->getPrefixInfo($table);
    foreach ($this->connection->getConnection()->selectCollection($prefixInfo['table'])->listIndexes(['session' => $this->connection->getMongodbSession()]) as $indexInfo) {
      if ($indexInfo->getName() == $index_name) {
        return $indexInfo;
      }
    }

    return FALSE;
  }

  /**
   * Helper function for creating keys and/or indexes.
   *
   * @param string $table
   *   The name of the table to create keys and/or indexes for.
   * @param array $spec
   *   The key specification for the new keys and/or indexes.
   */
  protected function createKeys($table, $spec) {
    if (!empty($spec['primary key'])) {
      $this->addPrimaryKey($table, $spec['primary key']);
    }
    if (!empty($spec['unique keys'])) {
      foreach ($spec['unique keys'] as $key => $fields) {
        $this->addUniqueKey($table, $key, $fields);
      }
    }
    if (!empty($spec['indexes'])) {
      foreach ($spec['indexes'] as $index => $fields) {
        $this->addIndex($table, $index, $fields, []);
      }
    }
  }

  /**
   * Helper function to create the MongoDB createIndex() $key parameter.
   *
   * @param array $fields
   *   The array with field names to create the index for. If a field name is an
   *   array the first element is the field name and the second one is 'ASC' or
   *   'DESC'.
   *
   * @return array
   *   An keyed array with the keys being the field names and the value being 1
   *   for ascending or -1 for descending.
   */
  protected function createKeyArray($fields) {
    $return = [];
    foreach ($fields as $field) {
      if (is_array($field)) {
        $return[$field[0]] = strtoupper($field[1]) == 'DESC' ? -1 : 1;
      }
      else {
        $return[$field] = 1;
      }
    }
    return $return;
  }

  /**
   * Check for the existence of the primary key.
   *
   * @param string $table
   *   The table to be altered.
   *
   * @returns bool
   *   Returns TRUE when the given table has a primary key or FALSE when it does
   *   not.
   */
  public function primaryKeyExists(string $table): bool {
    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist.
      return FALSE;
    }

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
    $index_name = $this->ensureIdentifiersLength($base_table_name, $embedded_full_path, 'pkey');
    $prefixInfo = $this->getPrefixInfo($base_table_name);
    foreach ($this->connection->getConnection()->selectCollection($prefixInfo['table'])->listIndexes(['session' => $this->connection->getMongodbSession()]) as $indexInfo) {
      if ($indexInfo->getName() == $index_name) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addPrimaryKey($table, $fields) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot add primary key to table $table: table doesn't exist.");
    }

    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("Cannot add primary key to table $table, because the base table doesn't exists.");
    }

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
    $constraint = $embedded_full_path . '__pkey';
    if ($this->constraintExists($base_table_name, $constraint)) {
      throw new SchemaObjectExistsException("Cannot add primary key to table $table: primary key already exists.");
    }

    // Sometimes working with MongoDB session the method constraintExists()
    // returns the wrong result. Therefor catch the thrown exception when we try
    // to create the primary key index.
    try {
      $index_name = $this->ensureIdentifiersLength($base_table_name, $embedded_full_path, 'pkey');
      $prefixInfo = $this->getPrefixInfo($base_table_name);
      if ($base_table_name == $table) {
        $this->connection->getConnection()->selectCollection($prefixInfo['table'])->createIndex(
          $this->createKeyArray($fields),
          [
            'name' => $index_name,
            'unique' => TRUE,
            'background' => $this->createIndexInBackground,
            'session' => $this->connection->getMongodbSession(),
          ]
        );
      }
      else {
        $embedded_fields = [];
        $partial_filter_expression = [];
        foreach ($fields as $field) {
          $field_full_path = $embedded_full_path . $field;
          $embedded_fields[$field_full_path] = 1;
          $partial_filter_expression[] = [$field_full_path => ['$exists' => TRUE]];
        }
        $partial_filter_expression = ['$and' => $partial_filter_expression];

        $this->connection->getConnection()->selectCollection($prefixInfo['table'])->createIndex(
          $embedded_fields,
          [
            'name' => $index_name,
            'unique' => TRUE,
            'partialFilterExpression' => $partial_filter_expression,
            'background' => $this->createIndexInBackground,
            'session' => $this->connection->getMongodbSession(),
          ],
        );
      }
    }
    catch (CommandException) {
      throw new SchemaObjectExistsException("Cannot add primary key to table $table: primary key already exists.");
    }

    // Test if unique index was created. If there is another unique index on the
    // same fields no exception is thrown.
    if (!$this->constraintExists($base_table_name, $constraint)) {
      throw new SchemaObjectExistsException("Cannot add primary key to table $table: there is already an unique index for the same fields.");
    }

    $this->tableInformation->setTablePrimaryKey($table, $fields)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function dropPrimaryKey($table) {
    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("The embedded table $table could not be created, because the base table doesn't exists.");
    }

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
    if (!$this->constraintExists($base_table_name, $embedded_full_path . '__pkey')) {
      return FALSE;
    }

    $index_name = $this->ensureIdentifiersLength($base_table_name, $embedded_full_path, 'pkey');
    $prefixInfo = $this->getPrefixInfo($base_table_name);
    $result = $this->connection->getConnection()->selectCollection($prefixInfo['table'])->dropIndex(
      $index_name,
      [
        'session' => $this->connection->getMongodbSession(),
      ],
    );

    $this->tableInformation->setTablePrimaryKey($table)->save();

    return $result && $result->ok;
  }

  /**
   * {@inheritdoc}
   */
  protected function findPrimaryKeyColumns($table) {
    if (!$this->tableExists($table)) {
      return FALSE;
    }

    if ($this->tableInformation->getTableBaseTable($table) == $table) {
      $prefixInfo = $this->getPrefixInfo($table);
      foreach ($this->connection->getConnection()->selectCollection($prefixInfo['table'])->listIndexes(['session' => $this->connection->getMongodbSession()]) as $indexInfo) {
        if (($indexInfo->getName() == '__pkey') && ($indexInfo instanceof IndexInfo)) {
          return array_keys($indexInfo->getKey());
        }
      }
    }
    else {
      return $this->tableInformation->getTablePrimaryKey($table);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function uniqueKeyExists($table, $name) {
    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist.
      return FALSE;
    }

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
    $index_name = $this->ensureIdentifiersLength($base_table_name, $embedded_full_path . $name, 'key');
    $prefixInfo = $this->getPrefixInfo($base_table_name);
    foreach ($this->connection->getConnection()->selectCollection($prefixInfo['table'])->listIndexes(['session' => $this->connection->getMongodbSession()]) as $indexInfo) {
      if ($indexInfo->getName() == $index_name) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addUniqueKey($table, $name, $fields) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot add unique key $name to table $table: table doesn't exist.");
    }

    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("The embedded table $table could not be created, because the base table doesn't exists.");
    }

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
    if ($this->constraintExists($base_table_name, $embedded_full_path . $name . '__key')) {
      throw new SchemaObjectExistsException("Cannot add unique key $name to table $table: unique key already exists.");
    }

    // Sometimes working with MongoDB session the method constraintExists()
    // returns the wrong result. Therefor catch the thrown exception when we try
    // to create the primary key index.
    try {
      $index_name = $this->ensureIdentifiersLength($base_table_name, $embedded_full_path . $name, 'key');
      $prefixInfo = $this->getPrefixInfo($base_table_name);
      if ($table == $base_table_name) {
        $this->connection->getConnection()->selectCollection($prefixInfo['table'])->createIndex(
          $this->createKeyArray($fields),
          [
            'name' => $index_name,
            'unique' => TRUE,
            'background' => $this->createIndexInBackground,
            'session' => $this->connection->getMongodbSession(),
          ],
        );
      }
      else {
        $embedded_fields = [];
        $partial_filter_expression = [];
        foreach ($fields as $field) {
          $field_full_path = $embedded_full_path . $field;
          $embedded_fields[$field_full_path] = 1;
          $partial_filter_expression[] = [$field_full_path => ['$exists' => TRUE]];
        }
        $partial_filter_expression = ['$and' => $partial_filter_expression];

        $this->connection->getConnection()->selectCollection($prefixInfo['table'])->createIndex(
          $embedded_fields,
          [
            'name' => $index_name,
            'unique' => TRUE,
            'partialFilterExpression' => $partial_filter_expression,
            'background' => $this->createIndexInBackground,
            'session' => $this->connection->getMongodbSession(),
          ],
        );
      }
    }
    catch (CommandException) {
      throw new SchemaObjectExistsException("Cannot add unique key $name to table $table: unique key already exists.");
    }

    // Test if unique index was created. If there is another unique index on the
    // same fields no exception is thrown.
    if (!$this->constraintExists($base_table_name, $embedded_full_path . $name . '__key')) {
      throw new SchemaObjectExistsException("Cannot add unique key to table $table: there is already an unique index for the same fields.");
    }

    $this->tableInformation->setTableUniqueKey($table, $name, $fields)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function dropUniqueKey($table, $name) {
    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("The unique key $name on table $table could not be dropped, because the base table doesn't exists.");
    }

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
    if (!$this->constraintExists($base_table_name, $embedded_full_path . $name . '__key')) {
      return FALSE;
    }

    $index_name = $this->ensureIdentifiersLength($base_table_name, $embedded_full_path . $name, 'key');
    $prefixInfo = $this->getPrefixInfo($base_table_name);
    $result = $this->connection->getConnection()->selectCollection($prefixInfo['table'])->dropIndex(
      $index_name,
      [
        'session' => $this->connection->getMongodbSession(),
      ],
    );

    $this->tableInformation->setTableUniqueKey($table, $name)->save();

    return $result && $result->ok;
  }

  /**
   * {@inheritdoc}
   */
  public function indexExists($table, $name) {
    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist.
      return FALSE;
    }

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
    $index_name = $this->ensureIdentifiersLength($base_table_name, $embedded_full_path . $name);
    $prefixInfo = $this->getPrefixInfo($base_table_name);
    foreach ($this->connection->getConnection()->selectCollection($prefixInfo['table'])->listIndexes(['session' => $this->connection->getMongodbSession()]) as $indexInfo) {
      if ($indexInfo->getName() == $index_name) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex($table, $name, $fields, array $spec) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot add index $name to table $table: table doesn't exist.");
    }

    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("The unique key $name on table $table could not be dropped, because the base table doesn't exists.");
    }

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
    $index_name = $this->ensureIdentifiersLength($base_table_name, $embedded_full_path . $name);
    if ($this->constraintExists($base_table_name, $index_name)) {
      throw new SchemaObjectExistsException("Cannot add index $name to table $table: index already exists.");
    }

    // Sometimes working with MongoDB session the method constraintExists()
    // returns the wrong result. Therefor catch the thrown exception when we try
    // to create the primary key index.
    try {
      $prefixInfo = $this->getPrefixInfo($base_table_name);
      if ($table == $base_table_name) {
        $this->connection->getConnection()->selectCollection($prefixInfo['table'])->createIndex(
          $this->createKeyArray($fields),
          [
            'name' => $index_name,
            'unique' => FALSE,
            'background' => $this->createIndexInBackground,
            'session' => $this->connection->getMongodbSession(),
          ],
        );
      }
      else {
        $embedded_fields = [];
        $partial_filter_expression = [];
        foreach ($fields as $field) {
          // It is possible in Drupal to add an array with the kind of type of
          // index that you would like. This is not supported by MongoDB.
          if (is_array($field)) {
            $field = reset($field);
          }

          if (is_string($field)) {
            $field_full_path = $embedded_full_path . $field;
            $embedded_fields[$field_full_path] = 1;
            $partial_filter_expression[] = [$field_full_path => ['$exists' => TRUE]];
          }
        }
        $partial_filter_expression = ['$and' => $partial_filter_expression];

        $this->connection->getConnection()->selectCollection($prefixInfo['table'])->createIndex(
          $embedded_fields,
          [
            'name' => $index_name,
            'unique' => FALSE,
            'partialFilterExpression' => $partial_filter_expression,
            'background' => $this->createIndexInBackground,
            'session' => $this->connection->getMongodbSession(),
          ],
        );
      }
    }
    catch (CommandException) {
      throw new SchemaObjectExistsException("Cannot add index $name to table $table: index already exists.");
    }

    // Test if index was created. If there is another index on the same fields
    // the following exception is thrown.
    if (!$this->constraintExists($base_table_name, $index_name)) {
      throw new SchemaObjectExistsException("Cannot add index to $name to table $base_table_name: there is already an index for the same fields.");
    }

    $this->tableInformation->setTableIndex($table, $name, $fields)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function dropIndex($table, $name) {
    $base_table_name = $this->tableInformation->getTableBaseTable($table);
    if (empty($base_table_name) || !$this->tableExists($base_table_name)) {
      // If there is no base table or the base table does not exist, throw an
      // exception.
      throw new SchemaObjectDoesNotExistException("The index $name on table $table could not be dropped, because the base table doesn't exists.");
    }

    $embedded_full_path = $this->tableInformation->getTableEmbeddedFullPath($table);
    $index_name = $this->ensureIdentifiersLength($base_table_name, $embedded_full_path . $name);
    if (!$this->constraintExists($base_table_name, $index_name)) {
      return FALSE;
    }

    $prefixInfo = $this->getPrefixInfo($base_table_name);
    $result = $this->connection->getConnection()->selectCollection($prefixInfo['table'])->dropIndex(
      $index_name,
      [
        'session' => $this->connection->getMongodbSession(),
      ],
    );

    $this->tableInformation->setTableIndex($table, $name)->save();

    return $result && $result->ok;
  }

  /**
   * Set the default value for a field.
   *
   * @param string $table
   *   The table to be altered.
   * @param string $field
   *   The field to be altered.
   * @param mixed $default
   *   Default value to be set. NULL for 'default NULL'.
   *
   * @throws \Drupal\Core\Database\SchemaObjectDoesNotExistException
   *   If the specified table or field doesn't exist.
   */
  public function fieldSetDefault($table, $field, $default) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot set the field default value for $field on table $table: table doesn't exist.");
    }

    $field_data = $this->tableInformation->getTableField($table, $field);
    if (empty($field_data)) {
      throw new SchemaObjectDoesNotExistException("Cannot set the field default value for $field on table $table: the field does not exist on the table.");
    }

    $field_data['default'] = $default;
    $this->tableInformation->setTableField($table, $field, $field_data)->save();
  }

  /**
   * Set a field to have no default value.
   *
   * @param string $table
   *   The table to be altered.
   * @param string $field
   *   The field to be altered.
   *
   * @throws \Drupal\Core\Database\SchemaObjectDoesNotExistException
   *   If the specified table or field doesn't exist.
   */
  public function fieldSetNoDefault($table, $field) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot unset the field default value for $field on table $table: table doesn't exist.");
    }

    $field_data = $this->tableInformation->getTableField($table, $field);
    if (empty($field_data)) {
      throw new SchemaObjectDoesNotExistException("Cannot unset the field default value for $field on table $table: the field does not exist on the table.");
    }

    unset($field_data['default']);
    $this->tableInformation->setTableField($table, $field, $field_data)->save();
  }

  /**
   * Helper function: check if a constraint (PK, FK, UK) exists.
   *
   * @param string $table
   *   The name of the table.
   * @param string $name
   *   The name of the constraint (typically 'pkey' or '[constraint]__key').
   *
   * @return bool
   *   TRUE if the constraint exists, FALSE otherwise.
   */
  public function constraintExists(string $table, string $name) {
    // ::ensureIdentifiersLength() expects three parameters. Thus we split our
    // constraint name in a proper name and a type.
    if ($name == 'pkey') {
      $type = $name;
      $name = '';
    }
    else {
      $pos = strrpos($name, '__');
      $type = substr($name, $pos + 2);
      $name = substr($name, 0, $pos);
    }
    $constraint_name = $this->ensureIdentifiersLength($table, $name, $type);
    $prefixInfo = $this->getPrefixInfo($table);
    foreach ($this->connection->getConnection()->selectCollection($prefixInfo['table'])->listIndexes(['session' => $this->connection->getMongodbSession()]) as $indexInfo) {
      if ($indexInfo->getName() == $constraint_name) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Make sure to limit identifiers according to MongoDB compiled in length.
   *
   * MongoDB allows in standard configuration no longer identifiers than 128
   * chars for indexes, primary keys, and constraints. So we map all identifiers
   * that are too long to drupal_base64hash_tag, where tag is one of:
   *   - idx for indexes
   *   - key for constraints
   *   - pkey for primary keys.
   *
   * @param string $table
   *   The name of the table in drupal (no prefixing).
   * @param string $name
   *   The name of the index in drupal (no prefixing).
   * @param string $type
   *   The type of the index:
   *   - idx for indexes
   *   - key for constraints
   *   - pkey for primary keys.
   *
   * @return string
   *   The index/constraint/pkey identifier
   */
  protected function ensureIdentifiersLength(string $table, string $name, string $type = 'idx'): string {
    $connectionOptions = $this->connection->getConnectionOptions();
    $prefixInfo = $this->getPrefixInfo($table);

    // Subtract 3 for the 2 dots and 1 dollar sign between database, collection
    // and index names.
    $maxIdentifierLength = $this->maxIdentifierLength - 3;
    $maxIdentifierLength -= strlen($connectionOptions['database']);
    $maxIdentifierLength -= strlen($prefixInfo['table']);

    // Index names must be unique within a collection.
    $identifierName = $name . '__' . $type;
    if (strlen($identifierName) > $maxIdentifierLength) {
      $saveIdentifier = 'drupal_' . $this->hashBase64($identifierName) . '__' . $type;
    }
    else {
      $saveIdentifier = $identifierName;
    }

    return $saveIdentifier;
  }

  /**
   * Retrieve a table or column comment.
   */
  public function getComment($table, $column = NULL) {
    if ($column) {
      return $this->tableInformation->getTableField($table, $column)['description'] ?? '';
    }
    else {
      return $this->tableInformation->getTable($table)['description'] ?? '';
    }
  }

  /**
   * Calculates a base-64 encoded, MongoDB-safe sha-256 hash.
   *
   * For more information: https://docs.mongodb.com/manual/reference/limits/.
   *
   * @param mixed $data
   *   String to be hashed.
   *
   * @return string
   *   A base-64 encoded sha-256 hash, with /, \ and . replaced with _ and any
   *   space, ", $, *, <, >, :, | and ? padding characters removed.
   */
  protected function hashBase64($data): string {
    $hash = base64_encode(hash('sha256', $data, TRUE));
    // Modify the hash so it's safe to use in MongoDB identifiers.
    return strtr($hash, [
      '/' => '_',
      '\\' => '_',
      '.' => '_',
      ' ' => '',
      '"' => '',
      '$' => '',
      '*' => '',
      '<' => '',
      '>' => '',
      ':' => '',
      '|' => '',
      '?' => '',
    ]);
  }

}
