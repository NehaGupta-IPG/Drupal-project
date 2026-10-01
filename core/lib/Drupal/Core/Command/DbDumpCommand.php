<?php

namespace Drupal\Core\Command;

use Drupal\Component\Utility\Variable;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\ColumnType;
use Drupal\Core\Database\SchemaDefinition\FloatValue;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\NullValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\StringValue;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Database\SchemaDefinition\UniqueKey;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarExporter\VarExporter;

/**
 * Provides a command to dump the current database to a script.
 *
 * This script exports all tables in the given database, and all data (except
 * for tables denoted as schema-only). The resulting script creates the tables
 * and populates them with the exported data.
 *
 * @todo This command is currently only compatible with MySQL. Making it
 *   backend-agnostic will require \Drupal\Core\Database\Schema support the
 *   ability to retrieve table schema information. Note that using a raw
 *   SQL dump file here (eg, generated from mysqldump or pg_dump) is not an
 *   option since these tend to still be database-backend specific.
 * @see https://www.drupal.org/node/301038
 *
 * @see \Drupal\Core\Command\DbDumpApplication
 */
class DbDumpCommand extends DbCommandBase {

  /**
   * An array of table patterns to exclude completely.
   *
   * This excludes any lingering tables generated during test runs.
   *
   * @var array
   */
  protected $excludeTables = ['test[0-9]+'];

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this->setName('dump-database-d8-mysql')
      ->setDescription('Dump the current database to a generation script')
      ->addOption('schema-only', NULL, InputOption::VALUE_OPTIONAL, 'A comma separated list of tables to only export the schema without data.', 'cache.*,sessions,watchdog')
      ->addOption('insert-count', NULL, InputOption::VALUE_OPTIONAL, ' The number of rows to insert in a single SQL statement.', 1000);
    parent::configure();
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $connection = $this->getDatabaseConnection($input);

    // If not explicitly set, disable ANSI which will break generated php.
    if ($input->hasParameterOption(['--ansi']) !== TRUE) {
      $output->setDecorated(FALSE);
    }

    $schema_tables = $input->getOption('schema-only');
    $schema_tables = explode(',', $schema_tables);
    $insert_count = (int) $input->getOption('insert-count');

    $output->writeln($this->generateScript($connection, $schema_tables, $insert_count), OutputInterface::OUTPUT_RAW);
    return 0;
  }

  /**
   * Generates the database script.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param array $schema_only
   *   Table patterns for which to only dump the schema, no data.
   * @param int $insert_count
   *   The number of rows to insert in a single statement.
   *
   * @return string
   *   The PHP script.
   */
  protected function generateScript(Connection $connection, array $schema_only = [], int $insert_count = 1000) {
    $tables = '';

    $schema_only_patterns = [];
    foreach ($schema_only as $match) {
      $schema_only_patterns[] = '/^' . $match . '$/';
    }

    /** @var list<\Drupal\Core\Database\SchemaDefinition\Table> $table_definitions */
    $table_definitions = [];
    foreach ($this->getTables($connection) as $table) {
      $table_definition = $this->getTableSchema($connection, $table);
      $table_definitions[] = $table_definition;
      // Check for schema only.
      if (empty($schema_only_patterns) || preg_replace($schema_only_patterns, '', $table)) {
        $data = $this->getTableData($connection, $table);
      }
      else {
        $data = [];
      }
      $tables .= $this->getTableScript($table, $table_definition, $data, $insert_count);
    }

    // Create the schema with all the tables dumped.
    $schema = new Schema(
      type: SchemaDefinitionType::Dump,
      name: 'db-dump',
      tables: $table_definitions,
    );

    $script = $this->getTemplate();
    // Substitute in the version.
    $script = str_replace('{{VERSION}}', \Drupal::VERSION, $script);
    // Substitute in the schema.
    $script = str_replace('{{SCHEMA}}', VarExporter::export($schema), $script);
    // Substitute in the tables.
    $script = str_replace('{{TABLES}}', trim($tables), $script);
    return trim($script);
  }

  /**
   * Returns a list of tables, not including those set to be excluded.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   *
   * @return list<string>
   *   An array of table names.
   */
  protected function getTables(Connection $connection): array {
    $tables = array_values($connection->schema()->findTables('%'));

    foreach ($tables as $key => $table) {
      // Remove any explicitly excluded tables.
      foreach ($this->excludeTables as $pattern) {
        if (preg_match('/^' . $pattern . '$/', $table)) {
          unset($tables[$key]);
        }
      }
    }

    // Keep the table names sorted alphabetically.
    asort($tables);

    return $tables;
  }

  /**
   * Returns a schema array for a given table.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $table
   *   The table name.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Table
   *   A table definition.
   *
   * @todo This implementation is hard-coded for MySQL.
   */
  protected function getTableSchema(Connection $connection, string $table): Table {
    // Check this is MySQL.
    if ($connection->databaseType() !== 'mysql') {
      throw new \RuntimeException('This script can only be used with MySQL database backends.');
    }

    $query = $connection->query("SHOW FULL COLUMNS FROM {" . $table . "}");
    $columns = [];
    while (($row = $query->fetchAssoc()) !== FALSE) {
      $column_args = ['name' => $row['Field']];
      // Parse out the field type and meta information.
      preg_match('@([a-z]+)(?:\((\d+)(?:,(\d+))?\))?\s*(unsigned)?@', $row['Type'], $matches);
      $type = $this->fieldTypeMap($connection, $matches[1]);
      if ($row['Extra'] === 'auto_increment') {
        // If this is an auto increment, then the type is 'serial'.
        $type = ColumnType::Serial;
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
      if ($size = $this->fieldSizeMap($connection, $matches[1])) {
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
            ColumnType::Int => new IntValue($default),
            ColumnType::Float, ColumnType::Numeric => new FloatValue($default),
            default => new StringValue($default),
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
      'columns' => $columns,
      'dbSpecificExtra' => [
        'mysql' => [
          'mysql_character_set' => $this->getTableCollation($connection, $table),
        ],
      ],
    ];
    // Set primary key, unique keys, and indexes.
    $table_args = array_merge($table_args, $this->getTableIndexes($connection, $table));

    return new Table(...$table_args);
  }

  /**
   * Adds primary key, unique keys, and index information to the schema.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $table
   *   The table to find indexes for.
   *
   * @return array
   *   The indexes as schema definition objects.
   */
  protected function getTableIndexes(Connection $connection, string $table): array {
    // Note, this query doesn't support ordering, so that is worked around
    // below by keying the array on Seq_in_index.
    $query = $connection->query("SHOW INDEX FROM {" . $table . "}");

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
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $table
   *   The table to find indexes for.
   *
   * @return string
   *   The table charset.
   */
  protected function getTableCollation(Connection $connection, string $table): string {
    // Remove identifier quotes from the table name. See
    // \Drupal\mysql\Driver\Database\mysql\Connection::$identifierQuotes.
    $table = trim($connection->prefixTables('{' . $table . '}'), '"');
    $query = $connection->query("SHOW TABLE STATUS WHERE NAME = :table_name", [':table_name' => $table]);
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
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $table
   *   The table to query.
   *
   * @return array
   *   The data from the table as an array.
   */
  protected function getTableData(Connection $connection, string $table): array {
    $order = $this->getFieldOrder($connection, $table);
    $query = $connection->query("SELECT * FROM {" . $table . "} " . $order);
    $results = [];
    while (($row = $query->fetchAssoc()) !== FALSE) {
      $results[] = $row;
    }
    return $results;
  }

  /**
   * Given a database field type, return a Drupal type.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $type
   *   The MySQL field type.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\ColumnType|string
   *   The Drupal schema enumerated column type. If there is no mapping, the
   *   original field type is returned as a string.
   */
  protected function fieldTypeMap(Connection $connection, string $type): ColumnType|string {
    // Convert everything to lowercase.
    $map = array_map('strtolower', $connection->schema()->getFieldTypeMap());
    $map = array_flip($map);

    // The MySql map contains type:size. Remove the size part.
    $mapped_type = isset($map[$type]) ? explode(':', $map[$type])[0] : $type;
    $return_type = ColumnType::tryFrom($mapped_type);
    return $return_type ?? $mapped_type;
  }

  /**
   * Given a database field type, return a Drupal size.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $type
   *   The MySQL field type.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\ColumnSize|null
   *   The Drupal schema enumerated field size, or NULL if there is no mapping,
   */
  protected function fieldSizeMap(Connection $connection, string $type): ColumnSize|null {
    // Convert everything to lowercase.
    $map = array_map('strtolower', $connection->schema()->getFieldTypeMap());
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
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $table
   *   The table name.
   *
   * @return string
   *   The order string to append to the query.
   */
  protected function getFieldOrder(Connection $connection, string $table): string {
    // @todo this is MySQL only since there are no Database API functions for
    // table column data.
    // @todo this code is duplicated in `core/scripts/migrate-db.sh`.
    $connection_info = $connection->getConnectionOptions();
    // Order by primary keys.
    $order = '';
    $query = "SELECT `COLUMN_NAME` FROM `information_schema`.`COLUMNS`
    WHERE (`TABLE_SCHEMA` = '" . $connection_info['database'] . "')
    AND (`TABLE_NAME` = '{" . $table . "}') AND (`COLUMN_KEY` = 'PRI')
    ORDER BY COLUMN_NAME";
    $results = $connection->query($query);
    while (($row = $results->fetchAssoc()) !== FALSE) {
      $order .= $row['COLUMN_NAME'] . ', ';
    }
    if (!empty($order)) {
      $order = ' ORDER BY ' . rtrim($order, ', ');
    }
    return $order;
  }

  /**
   * The script template.
   *
   * @return string
   *   The template for the generated PHP script.
   */
  protected function getTemplate(): string {
    // The template contains an instruction for the file to be ignored by PHPCS.
    // This is because the files can be huge and coding standards are
    // irrelevant.
    $script = <<<'END_OF_SCRIPT'
<?php
// phpcs:ignoreFile
/**
 * @file
 * A database agnostic dump for testing purposes.
 *
 * This file was generated by the Drupal {{VERSION}} db-tools.php script.
 */

use Drupal\Core\Database\Database;

$schema_dump = {{SCHEMA}};

$connection = Database::getConnection();
// Ensure any tables with a serial column with a value of 0 are created as
// expected.
if ($connection->databaseType() === 'mysql') {
  $sql_mode = $connection->query("SELECT @@sql_mode;")->fetchField();
  $connection->query("SET sql_mode = '$sql_mode,NO_AUTO_VALUE_ON_ZERO'");
}

// Build the schema.
$connection->schema()->createSchemaFromDefinition($schema_dump);

// Insert data in the tables.
{{TABLES}}

// Reset the SQL mode.
if ($connection->databaseType() === 'mysql') {
  $connection->query("SET sql_mode = '$sql_mode'");
}
END_OF_SCRIPT;
    return $script;
  }

  /**
   * The part of the script for each table.
   *
   * @param string $table
   *   Table name.
   * @param \Drupal\Core\Database\SchemaDefinition\Table $tableDefinition
   *   Drupal schema definition for the table.
   * @param array $data
   *   Data for the table.
   * @param int $insert_count
   *   The number of rows to insert in a single statement.
   *
   * @return string
   *   The table create statement, and if there is data, the insert command.
   */
  protected function getTableScript(string $table, Table $tableDefinition, array $data, int $insert_count = 1000): string {
    $output = '';
    if (!empty($data)) {
      $data_chunks = array_chunk($data, $insert_count);
      foreach ($data_chunks as $data_chunk) {
        $insert = '';
        foreach ($data_chunk as $record) {
          $insert .= "->values(" . Variable::export($record) . ")\n";
        }
        $fields = Variable::export(array_keys($tableDefinition->toArray()['fields']));
        $output .= <<<EOT
\$connection->insert('$table')
->fields($fields)
{$insert}->execute();

EOT;
      }
      $output .= "\n\n";
    }
    return $output;
  }

}
