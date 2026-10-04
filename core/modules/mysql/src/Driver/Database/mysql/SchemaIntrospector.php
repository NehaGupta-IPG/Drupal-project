<?php

declare(strict_types=1);

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Database\Exception\SchemaPrimaryKeyMustBeDroppedException;
use Drupal\Core\Database\Schema as DatabaseSchema;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\ColumnType;
use Drupal\Core\Database\SchemaDefinition\FloatValue;
use Drupal\Core\Database\SchemaDefinition\GeneratedColumnExpression;
use Drupal\Core\Database\SchemaDefinition\GeneratedColumnStorage;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\NullValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema as SchemaDefinition;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\StringValue;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Database\SchemaDefinition\UniqueKey;
use Drupal\Core\Database\SchemaException;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;
use Drupal\Core\Database\SchemaObjectExistsException;

/**
 * MySQL implementation of \Drupal\Core\Database\SchemaIntrospector.
 */
class SchemaIntrospector {

  /**
   * Constructor.
   *
   * @param Connection $connection
   *   The Drupal connection.
   * @param array $includePatterns
   *   (Optional) An array of database object name patterns to include. If not
   *   specified, all objects in the current database schema of the connection
   *   will be introspected.
   * @param array $excludePatterns
   *   (Optional) An array of database object name patterns to exclude
   *   completely.
   */
  public function __construct(
    protected readonly Connection $connection,
    protected readonly array $includePatterns,
    protected readonly array $excludePatterns,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): SchemaDefinition {
    /** @var list<\Drupal\Core\Database\SchemaDefinition\Table> $tableDefinitions */
    $tableDefinitions = [];
    foreach ($this->getTableNames() as $tableName) {
      $tableDefinition = $this->getTableSchema($tableName);
      $tableDefinitions[] = $tableDefinition;
    }

    // Create the schema with all the tables dumped.
    return new SchemaDefinition(
      type: SchemaDefinitionType::Introspect,
      name: 'schema-introspect',
      tables: $tableDefinitions,
    );
  }

  /**
   * Returns a list of tables, not including those set to be excluded.
   *
   * @return list<string>
   *   An array of table names.
   */
  protected function getTableNames(): array {
    $tables = array_values($this->connection->schema()->findTables('%'));

    $output = [];
    foreach ($tables as $key => $table) {
      // Included tables.
      foreach ($this->includePatterns as $pattern) {
        if (preg_match($pattern, $table)) {
          $output[$key] = $table;
        }
      }
      // Remove any explicitly excluded tables.
      foreach ($this->excludePatterns as $pattern) {
        if (preg_match($pattern, $table)) {
          unset($output[$key]);
        }
      }
    }

    // Keep the table names sorted alphabetically.
    asort($output);

    return $output;
  }

  /**
   * Returns a schema array for a given table.
   *
   * @param string $table
   *   The table name.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Table
   *   A table definition.
   */
  protected function getTableSchema(string $table): Table {
    $tableStatus = $this->connection->query("SHOW TABLE STATUS WHERE [Name] = '" . $this->connection->getPrefix() . "{$table}'")->fetchAssoc();
    $description = $tableStatus['Comment'] ?? NULL;

    $query = $this->connection->query("SHOW FULL COLUMNS FROM {" . $table . "}");
    $columns = [];
    while (($row = $query->fetchAssoc()) !== FALSE) {
      $column_args = ['name' => $row['Field']];

      if (!empty($row['Comment'])) {
        $column_args['description'] = $row['Comment'];
      }

      // Parse out the field type and meta information.
      preg_match('@([a-z]+)(?:\((\d+)(?:,(\d+))?\))?\s*(unsigned)?@', $row['Type'], $matches);
      $type = $this->fieldTypeMap($matches[1]);

      if ($row['Extra'] === 'auto_increment') {
        // If this is an auto increment, then the type is 'serial'.
        $type = ColumnType::Serial;
      }
      elseif ($row['Extra'] === 'VIRTUAL GENERATED') {
        $type = ColumnType::Generated;
        $column_args['storage'] = GeneratedColumnStorage::Virtual;
        $column_args['expression'] = new GeneratedColumnExpression('x2');
      }
      elseif ($row['Extra'] === 'STORED GENERATED') {
        $type = ColumnType::Generated;
        $column_args['storage'] = GeneratedColumnStorage::Stored;
        $column_args['expression'] = new GeneratedColumnExpression('x2');
      }

      if (!is_string($type)) {
        $column_args['type'] = $type;
      }
      else {
        $column_args['dbSpecificExtra'] = [
          'mysql' => [
            'mysql_type' => $type,
          ],
        ];
      }

      if ($type !== ColumnType::Serial) {
        $column_args['notNull'] = $row['Null'] === 'NO';
      }

      if ($size = $this->fieldSizeMap($matches[1])) {
        $column_args['size'] = $size;
      }

      if (isset($matches[2]) && $type === ColumnType::Numeric) {
        // Add precision and scale.
        $column_args['precision'] = (int) $matches[2];
        $column_args['scale'] = (int) $matches[3];
      }
      elseif ($type === 'time') {
        // @todo Core doesn't support these, but copied from `migrate-db.sh` for now.
        // Convert to varchar.
        $column_args['type'] = ColumnType::Varchar;
        $column_args['length'] = 100;
      }
      elseif ($type === 'datetime') {
        // Adjust for other database types.
        $column_args['dbSpecificExtra'] = [
          'mysql' => [
            'mysql_type' => 'datetime',
          ],
          'pgsql' => [
            'pgsql_type' => 'timestamp without time zone',
          ],
          'sqlite' => [
            'sqlite_type' => 'varchar',
          ],
          'sqlsrv' => [
            'sqlsrv_type' => 'smalldatetime',
          ],
        ];
      }
      elseif (!isset($column_args['size']) && (is_string($type) || in_array($type, [
        ColumnType::Char,
        ColumnType::Varchar,
        ColumnType::VarcharAscii,
        ColumnType::Text,
      ]))) {
        // Try use the provided length, if it doesn't exist default to 100. It's
        // not great but good enough for our dumps at this point.
        $column_args['length'] = (int) ($matches[2] ?? 100);
      }

      if (isset($row['Default'])) {
        $default = $row['Default'];
        if ($default === 'NULL') {
          $column_args['default'] = new NullValue();
        }
        else {
          $column_args['default'] = match ($type) {
            ColumnType::Int => new IntValue((int) $default),
            ColumnType::Float, ColumnType::Numeric => new FloatValue((float) $default),
            default => new StringValue((string) $default),
          };
        }
      }

      if ($type !== ColumnType::Serial && isset($matches[4])) {
        $column_args['unsigned'] = TRUE;
      }

      // Check for the 'varchar_ascii' type that should be 'binary'.
      if (isset($row['Collation']) && $row['Collation'] == 'ascii_bin') {
        $column_args['type'] = ColumnType::VarcharAscii;
        $column_args['binary'] = TRUE;
      }

      // Check for the non-binary 'varchar_ascii'.
      if (isset($row['Collation']) && $row['Collation'] == 'ascii_general_ci') {
        $column_args['type'] = ColumnType::VarcharAscii;
      }

      // Check for the 'utf8_bin' collation.
      if (isset($row['Collation']) && $row['Collation'] == 'utf8_bin') {
        $column_args['binary'] = TRUE;
      }

      $column = match ($column_args['type'] ?? NULL) {
        ColumnType::Char, ColumnType::Varchar, ColumnType::VarcharAscii => Column::{lcfirst($column_args['type']->name)}(
          name: $column_args['name'],
          description: $column_args['description'] ?? NULL,
          size: $column_args['size'] ?? NULL,
          notNull: $column_args['notNull'] ?? NULL,
          default: $column_args['default'] ?? NULL,
          length: $column_args['length'] ?? NULL,
          binary: $column_args['binary'] ?? NULL,
        ),
        ColumnType::Text => Column::{lcfirst($column_args['type']->name)}(
          name: $column_args['name'],
          description: $column_args['description'] ?? NULL,
          serialize: $column_args['serialize'] ?? NULL,
          size: $column_args['size'] ?? NULL,
          notNull: $column_args['notNull'] ?? NULL,
          default: $column_args['default'] ?? NULL,
          length: $column_args['length'] ?? NULL,
          binary: $column_args['binary'] ?? NULL,
        ),
        ColumnType::Int, ColumnType::Float => Column::{lcfirst($column_args['type']->name)}(
          name: $column_args['name'],
          description: $column_args['description'] ?? NULL,
          size: $column_args['size'] ?? NULL,
          notNull: $column_args['notNull'] ?? NULL,
          default: $column_args['default'] ?? NULL,
          unsigned: $column_args['unsigned'] ?? NULL,
        ),
        ColumnType::Serial => Column::{lcfirst($column_args['type']->name)}(
          name: $column_args['name'],
          description: $column_args['description'] ?? NULL,
          size: $column_args['size'] ?? NULL,
        ),
        ColumnType::Numeric => Column::{lcfirst($column_args['type']->name)}(
          name: $column_args['name'],
          description: $column_args['description'] ?? NULL,
          size: $column_args['size'] ?? NULL,
          notNull: $column_args['notNull'] ?? NULL,
          default: $column_args['default'] ?? NULL,
          unsigned: $column_args['unsigned'] ?? NULL,
          precision: $column_args['precision'] ?? NULL,
          scale: $column_args['scale'] ?? NULL,
        ),
        ColumnType::Blob => Column::{lcfirst($column_args['type']->name)}(
          name: $column_args['name'],
          description: $column_args['description'] ?? NULL,
          serialize: $column_args['serialize'] ?? NULL,
          size: $column_args['size'] ?? NULL,
          notNull: $column_args['notNull'] ?? NULL,
          default: $column_args['default'] ?? NULL,
        ),
        ColumnType::Generated => Column::{lcfirst($column_args['type']->name)}(
          name: $column_args['name'],
          type: $column_args['type'],
          storage: $column_args['storage'],
          expression: $column_args['expression'],
          description: $column_args['description'] ?? NULL,
          size: $column_args['size'] ?? NULL,
          length: $column_args['length'] ?? NULL,
          unsigned: $column_args['unsigned'] ?? NULL,
          precision: $column_args['precision'] ?? NULL,
          scale: $column_args['scale'] ?? NULL,
          binary: $column_args['binary'] ?? NULL,
          dbSpecificExtra: $column_args['dbSpecificExtra'] ?? NULL,
        ),
        default => Column::create(
          name: $column_args['name'],
          type: $column_args['type'] ?? NULL,
          description: $column_args['description'] ?? NULL,
          serialize: $column_args['serialize'] ?? NULL,
          size: $column_args['size'] ?? NULL,
          notNull: $column_args['notNull'] ?? NULL,
          default: $column_args['default'] ?? NULL,
          length: $column_args['length'] ?? NULL,
          unsigned: $column_args['unsigned'] ?? NULL,
          precision: $column_args['precision'] ?? NULL,
          scale: $column_args['scale'] ?? NULL,
          binary: $column_args['binary'] ?? NULL,
          dbSpecificExtra: $column_args['dbSpecificExtra'] ?? NULL,
        ),
      };

      $columns[] = $column;
    }

    $table_args = [
      'name' => $table,
      'description' => $description,
      'columns' => $columns,
      'dbSpecificExtra' => [
        'mysql' => [
          'mysql_character_set' => $this->getTableCollation($table),
        ],
      ],
    ];
    // Set primary key, unique keys, and indexes.
    $table_args = array_merge($table_args, $this->getTableIndexes($table));

    return new Table(...$table_args);
  }

  /**
   * Adds primary key, unique keys, and index information to the schema.
   *
   * @param string $table
   *   The table to find indexes for.
   *
   * @return array
   *   The indexes as schema definition objects.
   */
  protected function getTableIndexes(string $table): array {
    // Note, this query doesn't support ordering, so that is worked around
    // below by keying the array on Seq_in_index.
    $query = $this->connection->query("SHOW INDEX FROM {" . $table . "}");

    $definition = [];
    while (($row = $query->fetchAssoc()) !== FALSE) {
      $index_name = $row['Key_name'];
      $column = $row['Column_name'];
      // Key the arrays by the index sequence for proper ordering (start at 0).
      $order = $row['Seq_in_index'] - 1;

      // If specified, add length to the index.
      if ($row['Sub_part']) {
        $column = [$column, (int) $row['Sub_part']];
      }

      if ($index_name === 'PRIMARY') {
        $definition['primary key'][$order] = $column;
      }
      elseif ($row['Non_unique'] == 0) {
        $definition['unique keys'][$index_name][$order] = $column;
      }
      else {
        $definition['indexes'][$index_name][$order] = $column;
      }
    }

    $ret = [];
    if (isset($definition['primary key'])) {
      ksort($definition['primary key']);
      $ret['primaryKey'] = new PrimaryKey($definition['primary key']);
    }
    if (isset($definition['unique keys'])) {
      foreach ($definition['unique keys'] as $name => $key) {
        ksort($key);
        $ret['uniqueKeys'][] = new UniqueKey(name: $name, columns: $key);
      }
    }
    if (isset($definition['indexes'])) {
      foreach ($definition['indexes'] as $name => $key) {
        ksort($key);
        $ret['indexes'][] = new Index(name: $name, columns: $key);
      }
    }

    return $ret;
  }

  /**
   * Returns the table charset.
   *
   * @param string $table
   *   The table to find indexes for.
   *
   * @return string
   *   The table charset.
   */
  protected function getTableCollation(string $table): string {
    // Remove identifier quotes from the table name. See
    // \Drupal\mysql\Driver\Database\mysql\Connection::$identifierQuotes.
    $table = trim($this->connection->prefixTables('{' . $table . '}'), '"');
    $query = $this->connection->query("SHOW TABLE STATUS WHERE NAME = :table_name", [':table_name' => $table]);
    $data = $query->fetchAssoc();

    // Map the collation to a character set. For example, 'utf8mb4_general_ci'
    // (MySQL 5) or 'utf8mb4_0900_ai_ci' (MySQL 8) will be mapped to 'utf8mb4'.
    [$charset] = explode('_', $data['Collation'], 2);

    return $charset;
  }

  /**
   * Gets all data from a given table.
   *
   * If a table is set to be schema only, and empty array is returned.
   *
   * @param string $table
   *   The table to query.
   *
   * @return array
   *   The data from the table as an array.
   */
  protected function getTableData(string $table): array {
    $order = $this->getFieldOrder($table);
    $query = $this->connection->query("SELECT * FROM {" . $table . "} " . $order);
    $results = [];
    while (($row = $query->fetchAssoc()) !== FALSE) {
      $results[] = $row;
    }
    return $results;
  }

  /**
   * Given a database field type, return a Drupal type.
   *
   * @param string $type
   *   The MySQL field type.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\ColumnType|string
   *   The Drupal schema enumerated column type. If there is no mapping, the
   *   original field type is returned as a string.
   */
  protected function fieldTypeMap(string $type): ColumnType|string {
    // Convert everything to lowercase.
    $map = array_map('strtolower', $this->connection->schema()->getFieldTypeMap());
    $map = array_flip($map);

    // The MySql map contains type:size. Remove the size part.
    $mapped_type = isset($map[$type]) ? explode(':', $map[$type])[0] : $type;
    $return_type = ColumnType::tryFrom($mapped_type);
    return $return_type ?? $mapped_type;
  }

  /**
   * Given a database field type, return a Drupal size.
   *
   * @param string $type
   *   The MySQL field type.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\ColumnSize|null
   *   The Drupal schema enumerated field size, or NULL if there is no mapping,
   */
  protected function fieldSizeMap(string $type): ColumnSize|null {
    // Convert everything to lowercase.
    $map = array_map('strtolower', $this->connection->schema()->getFieldTypeMap());
    $map = array_flip($map);

    // Do nothing if the field type is not defined.
    if (!isset($map[$type])) {
      return NULL;
    }

    $schema_type = explode(':', $map[$type])[0];
    // Only specify size on these types.
    if (in_array($schema_type, ['blob', 'float', 'int', 'text'])) {
      // The MySql map contains type:size. Remove the type part.
      $mapped_size = explode(':', $map[$type])[1];
      return ColumnSize::tryFrom($mapped_size);
    }

    return NULL;
  }

  /**
   * Gets field ordering for a given table.
   *
   * @param string $table
   *   The table name.
   *
   * @return string
   *   The order string to append to the query.
   */
  protected function getFieldOrder(string $table): string {
    // @todo this is MySQL only since there are no Database API functions for
    // table column data.
    // @todo this code is duplicated in `core/scripts/migrate-db.sh`.
    $connection_info = $this->connection->getConnectionOptions();
    // Order by primary keys.
    $order = '';
    $query = "SELECT `COLUMN_NAME` FROM `information_schema`.`COLUMNS`
    WHERE (`TABLE_SCHEMA` = '" . $connection_info['database'] . "')
    AND (`TABLE_NAME` = '{" . $table . "}') AND (`COLUMN_KEY` = 'PRI')
    ORDER BY COLUMN_NAME";
    $results = $this->connection->query($query);
    while (($row = $results->fetchAssoc()) !== FALSE) {
      $order .= $row['COLUMN_NAME'] . ', ';
    }
    if (!empty($order)) {
      $order = ' ORDER BY ' . rtrim($order, ', ');
    }
    return $order;
  }

}
