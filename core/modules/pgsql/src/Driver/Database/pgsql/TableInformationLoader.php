<?php

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;

// cSpell:ignore adbin adnum adrelid adsrc attisdropped attname attnum attrdef
// cSpell:ignore attrelid atttypid atttypmod nextval nspname regclass relkind
// cSpell:ignore relname relnamespace

/**
 * Loads and caches the blob and sequence information of tables.
 *
 * Fetches the information that insert, update and upsert queries and the
 * schema object need for every table of a connection with a single catalog
 * query, so that a request does not pay one query per table it writes to.
 * The information is cached persistently, so that a request with a warm
 * cache does not query the catalog at all; every schema change deletes the
 * cached information.
 *
 * Every connection owns one loader, available through
 * Connection::tableInformation(). The pgsql.table_information_prewarmer
 * service prewarms the cache of the default database connection.
 *
 * @see \Drupal\pgsql\Driver\Database\pgsql\Connection::tableInformation()
 * @see \Drupal\pgsql\TableInformationPreWarmer
 */
class TableInformationLoader {

  /**
   * The SQL to fetch the blob and serial attributes of tables.
   *
   * The bytea columns and sequences for a table can be found in pg_attribute,
   * which is significantly faster than querying the information_schema. The
   * data type of a field can be found by lookup of the attribute ID, and the
   * default value must be extracted from the node tree for the attribute
   * definition instead of the historical human-readable column, adsrc.
   */
  protected const TABLE_ATTRIBUTES_SQL = <<<'EOD'
SELECT pg_attribute.attrelid, pg_attribute.attnum, pg_attribute.attname AS column_name, format_type(pg_attribute.atttypid, pg_attribute.atttypmod) AS data_type, pg_get_expr(pg_attrdef.adbin, pg_attribute.attrelid) AS column_default
FROM pg_attribute
LEFT JOIN pg_attrdef ON pg_attrdef.adrelid = pg_attribute.attrelid AND pg_attrdef.adnum = pg_attribute.attnum
WHERE pg_attribute.attnum > 0
AND NOT pg_attribute.attisdropped
AND (format_type(pg_attribute.atttypid, pg_attribute.atttypmod) = 'bytea'
OR pg_get_expr(pg_attrdef.adbin, pg_attribute.attrelid) LIKE 'nextval%')
EOD;

  /**
   * The information about blob columns and sequences of tables.
   *
   * Keyed by "schema.prefixed_table". Filled for all tables at once by
   * loadAll(); entries for temporary tables and for tables introspected
   * individually are added through loadTable().
   *
   * This is populated before the persistent cache is written, so that the
   * cache write's own insert query finds the information here without
   * recursing into the load.
   *
   * @var \stdClass[]
   */
  protected array $tableInformation = [];

  /**
   * The schemas for which the information of all tables has been loaded.
   *
   * A schema deliberately stays loaded when a single table is reset: the
   * table is then introspected individually instead of loading everything
   * again.
   *
   * @var array
   */
  protected array $loadedSchemas = [];

  /**
   * The schema of the connection, defaults to public.
   */
  protected string $defaultSchema;

  public function __construct(
    protected readonly Connection $connection,
  ) {
    $this->defaultSchema = $connection->getConnectionOptions()['schema'] ?? 'public';
  }

  /**
   * Returns the blob and sequence information of one table.
   *
   * @param string $table
   *   The non-prefixed name of the table.
   *
   * @return \stdClass
   *   An object with two member variables:
   *   - 'blob_fields' that lists all the blob fields in the table.
   *   - 'sequences' that lists the sequences used in that table.
   */
  public function getTableInformation(string $table): \stdClass {
    $prefixed_table = $this->connection->getPrefix() . $table;

    // Take into account that temporary tables are stored in a different
    // schema. \Drupal\Core\Database\Connection::generateTemporaryTableName()
    // sets the 'db_temporary_' prefix to all temporary tables.
    if (str_contains($table, 'db_temporary_')) {
      // The pg_temp alias always resolves to the temporary namespace of the
      // current session.
      $key = 'pg_temp.' . $prefixed_table;
    }
    else {
      $key = $this->defaultSchema . '.' . $prefixed_table;

      // Collect the information for all tables of this connection with a
      // single catalog query, so that a request pays the introspection cost
      // at most once no matter how many tables it writes to.
      $this->loadAll();
    }

    // Fall back to introspecting the single table: temporary tables live in
    // a session specific schema that the bulk load cannot see, and a table
    // may have been created after the load.
    return $this->get($key) ?? $this->loadTable($key);
  }

  /**
   * Resets the information of one table, after a schema change.
   *
   * @param string $table
   *   The non-prefixed name of the table.
   */
  public function resetTable(string $table): void {
    $this->reset($this->defaultSchema . '.' . $this->connection->getPrefix() . $table);
  }

  /**
   * Returns the information for one table, if known.
   *
   * @param string $key
   *   The table key, "schema.prefixed_table".
   *
   * @return \stdClass|null
   *   The table information, or NULL for an unknown table.
   */
  public function get(string $key): ?\stdClass {
    return $this->tableInformation[$key] ?? NULL;
  }

  /**
   * Loads the blob and sequence information for a single table.
   *
   * Used for tables that loadAll() cannot see: temporary tables, and tables
   * created after the load. These entries are never persisted.
   *
   * @param string $key
   *   The table key, "schema.prefixed_table".
   *
   * @return \stdClass
   *   The table information. The information of a table that does not exist
   *   is not stored, so that it is collected again once the table has been
   *   created.
   */
  protected function loadTable(string $key): \stdClass {
    $table_information = (object) [
      'blob_fields' => [],
      'sequences' => [],
    ];

    // Quote the two parts of the key for the lookup. The pg_temp alias of
    // temporary tables keeps its special meaning when quoted.
    [$schema, $table] = explode('.', $key, 2);
    $quoted_key = '"' . $schema . '"."' . $table . '"';

    // Resolve the table name with to_regclass(), which returns NULL for a
    // table that does not exist instead of raising an error. The query can
    // therefore not fail and abort a wrapping transaction.
    $sql = 'SELECT relation.oid AS relation_oid, ai.column_name, ai.data_type, ai.column_default
FROM (SELECT to_regclass(:key) AS oid) relation
LEFT JOIN (' . static::TABLE_ATTRIBUTES_SQL . ') ai ON ai.attrelid = relation.oid
ORDER BY ai.attnum';
    $result = $this->connection->query($sql, [
      ':key' => $quoted_key,
    ]);

    $table_exists = FALSE;
    foreach ($result as $row) {
      $table_exists = $table_exists || isset($row->relation_oid);
      // A table without any blob or serial field, and a table that does not
      // exist, both return a single row with NULL attribute columns.
      if ($row->column_name !== NULL) {
        $this->parseColumn($table_information, $row);
      }
    }

    if ($table_exists) {
      $this->tableInformation[$key] = $table_information;
    }
    return $table_information;
  }

  /**
   * Loads the blob and sequence information for all tables at once.
   *
   * Tables without any blob or serial field get an entry as well, so their
   * lookup does not fall back to the single table introspection in
   * getTableInformation().
   *
   * @param string|null $schema
   *   (optional) The schema to load the table information for. Defaults to
   *   the schema of the connection.
   */
  public function loadAll(?string $schema = NULL): void {
    $schema ??= $this->defaultSchema;
    if (isset($this->loadedSchemas[$schema])) {
      return;
    }

    $cache = $this->getCache();
    if ($cache && ($cached = $cache->get($this->getCacheId($schema)))) {
      // Keep the entries of temporary tables collected before this load.
      $this->tableInformation = $cached->data + $this->tableInformation;
      $this->loadedSchemas[$schema] = TRUE;
      return;
    }

    $sql = 'SELECT pg_class.relname AS table_name, ai.column_name, ai.data_type, ai.column_default
FROM pg_class
INNER JOIN pg_namespace ON pg_namespace.oid = pg_class.relnamespace
LEFT JOIN (' . static::TABLE_ATTRIBUTES_SQL . ') ai ON ai.attrelid = pg_class.oid
WHERE pg_namespace.nspname = :schema
AND pg_class.relkind = \'r\'
AND pg_class.relname LIKE :pattern
ORDER BY pg_class.relname, ai.attnum';
    $result = $this->connection->query($sql, [
      ':schema' => $schema,
      ':pattern' => $this->connection->escapeLike($this->connection->getPrefix()) . '%',
    ]);

    $tables = [];
    foreach ($result as $row) {
      $key = $schema . '.' . $row->table_name;
      if (!isset($tables[$key])) {
        $tables[$key] = (object) [
          'blob_fields' => [],
          'sequences' => [],
        ];
      }
      // A table without any blob or serial field returns a single row with
      // NULL attribute columns.
      if ($row->column_name !== NULL) {
        $this->parseColumn($tables[$key], $row);
      }
    }
    // Keep the entries of temporary tables collected before this load.
    $this->tableInformation = $tables + $this->tableInformation;
    $this->loadedSchemas[$schema] = TRUE;

    // Persist only after the property above is populated: writing the cache
    // is itself an insert into a table with a blob column, and that insert
    // must find the table information without querying again. Do not persist
    // inside a transaction: PostgreSQL DDL is transactional, so a rollback
    // would leave wrong information in cache backends that do not roll back
    // with the database. Only the loaded information is persisted, not the
    // session specific entries added through loadTable().
    if ($cache && !$this->connection->inTransaction()) {
      $cache->set($this->getCacheId($schema), $tables);
    }
  }

  /**
   * Adds one column of the catalog query result to the table information.
   *
   * @param \stdClass $table_information
   *   The table information to add the column to.
   * @param object $row
   *   The catalog query result row of a bytea or serial column.
   */
  protected function parseColumn(\stdClass $table_information, object $row): void {
    if ($row->data_type == 'bytea') {
      $table_information->blob_fields[$row->column_name] = TRUE;
    }
    elseif (preg_match("/nextval\('([^']+)'/", $row->column_default, $matches)) {
      // We must know of any sequences in the table structure to help us
      // return the last insert id. If there is more than 1 sequences the
      // first one (index 0 of the sequences array) will be used.
      $table_information->sequences[] = $matches[1];
      $table_information->serial_fields[] = $row->column_name;
    }
  }

  /**
   * Resets the information of one table, after a schema change.
   *
   * @param string $key
   *   The table key, "schema.prefixed_table".
   */
  protected function reset(string $key): void {
    unset($this->tableInformation[$key]);
    // Delete the persistent entry of the schema the table belongs to.
    [$schema] = explode('.', $key, 2);
    $this->getCache()?->delete($this->getCacheId($schema));
  }

  /**
   * Returns the cache ID for the table information of one schema.
   *
   * @param string $schema
   *   The schema to build the cache ID for. Deliberately without a default:
   *   the callers have already resolved which schema they operate on, and an
   *   accidentally omitted argument must fail instead of silently returning
   *   the cache ID of the connection's schema.
   */
  protected function getCacheId(string $schema): string {
    $options = $this->connection->getConnectionOptions();
    return 'pgsql_table_information:' . implode(':', [
      $options['host'] ?? '',
      $options['port'] ?? '',
      $options['database'] ?? '',
      $schema,
      $this->connection->getPrefix(),
    ]);
  }

  /**
   * Returns the cache bin for the table information, if available.
   *
   * The database layer must work without a container, for example in the
   * installer or when only \Drupal\Core\Database\Database is used, so the
   * cache is optional.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface|null
   *   The cache backend, or NULL when the container or the bin is not
   *   available.
   */
  protected function getCache(): ?CacheBackendInterface {
    if (\Drupal::hasContainer() && \Drupal::hasService('cache.bootstrap')) {
      return \Drupal::cache('bootstrap');
    }
    return NULL;
  }

}
