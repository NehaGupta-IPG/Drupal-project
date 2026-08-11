<?php

namespace Drupal\pgsql\Plugin\views\query;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\views\Plugin\views\query\CastSql;

// cspell:ignore attisdropped attname attnum attrelid atttypid nspname relname
// cspell:ignore relnamespace typcategory

/**
 * PostgreSQL-specific cast handling.
 *
 * @internal
 *   This class should only be used by the Views SQL query plugin.
 *
 * @see \Drupal\views\Plugin\views\query\Sql
 */
class PgsqlCastSql extends CastSql {

  use DependencySerializationTrait;

  /**
   * The type category of each column, keyed by prefixed table name.
   *
   * @var array<string, array<string, string>>
   */
  protected array $typeCategories = [];

  /**
   * Constructs a PgsqlCastSql object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(protected Connection $database) {}

  /**
   * {@inheritdoc}
   */
  public function getFieldAsInt(string $field): string {
    // A value that is not a number raises an error in PostgreSQL when it is
    // cast, and that error is not limited to the rows the query selects: a
    // single unrelated row can fail the whole query. Values that are not a
    // number become NULL instead, because they can never be equal to a number.
    return "CASE WHEN pg_input_is_valid(CAST($field AS TEXT), 'bigint') THEN CAST($field AS BIGINT) END";
  }

  /**
   * {@inheritdoc}
   */
  public function getComparableOperands(array $left, array $right): array {
    $left_category = $this->getTypeCategory($left);
    $right_category = $this->getTypeCategory($right);

    // PostgreSQL has no operator to compare a number with a string. Convert the
    // string operand to a number, which keeps any index on the numeric column
    // usable. Operands of the same category, and operands whose column is not
    // known, are compared as they are.
    if ($left_category === 'N' && $right_category === 'S') {
      $right['expression'] = $this->getFieldAsInt($right['expression']);
    }
    elseif ($left_category === 'S' && $right_category === 'N') {
      $left['expression'] = $this->getFieldAsInt($left['expression']);
    }

    return [$left['expression'], $right['expression']];
  }

  /**
   * Returns the type category of the column an operand refers to.
   *
   * @param array $operand
   *   An array describing the operand. See
   *   \Drupal\views\Plugin\views\query\CastSqlInterface::getComparableOperands()
   *   for the format.
   *
   * @return string|null
   *   The type category of the column, for example 'N' for the numeric types
   *   and 'S' for the string types, or NULL if the column is unknown.
   *
   * @see https://www.postgresql.org/docs/current/catalog-pg-type.html
   */
  protected function getTypeCategory(array $operand): ?string {
    if (empty($operand['table']) || empty($operand['column'])) {
      return NULL;
    }

    $table = $this->database->getPrefix() . $operand['table'];
    if (!isset($this->typeCategories[$table])) {
      // The PostgreSQL catalog is significantly faster to query than the
      // information schema. Tables that do not exist, for example the formulas
      // some joins use in place of a table, return no rows.
      $sql = <<<'EOD'
SELECT pg_attribute.attname AS column_name, pg_type.typcategory AS type_category
FROM pg_attribute
INNER JOIN pg_class ON pg_class.oid = pg_attribute.attrelid
INNER JOIN pg_namespace ON pg_namespace.oid = pg_class.relnamespace
INNER JOIN pg_type ON pg_type.oid = pg_attribute.atttypid
WHERE pg_class.relname = :table
AND pg_namespace.nspname = ANY (current_schemas(TRUE))
AND pg_attribute.attnum > 0
AND NOT pg_attribute.attisdropped
EOD;
      $this->typeCategories[$table] = $this->database
        ->query($sql, [':table' => $table])
        ->fetchAllKeyed();
    }

    return $this->typeCategories[$table][$operand['column']] ?? NULL;
  }

}
