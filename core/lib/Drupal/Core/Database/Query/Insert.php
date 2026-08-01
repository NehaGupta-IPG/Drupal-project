<?php

namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\InvalidQueryException;

/**
 * General class for an abstracted INSERT query.
 *
 * @ingroup database
 */
abstract class Insert extends Query implements \Countable {

  use InsertTrait;

  /**
   * A SelectQuery object to fetch the rows that should be inserted.
   *
   * @var \Drupal\Core\Database\Query\SelectInterface
   */
  protected $fromQuery;

  /**
   * Constructs an Insert object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A Connection object.
   * @param string $table
   *   Name of the table to associate with this query.
   * @param array $options
   *   Array of database options.
   */
  public function __construct($connection, $table, array $options = []) {
    parent::__construct($connection, $options);
    $this->table = $table;
  }

  /**
   * Sets the fromQuery on this InsertQuery object.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The query to fetch the rows that should be inserted.
   *
   * @return $this
   *   The called object.
   */
  public function from(SelectInterface $query) {
    $this->fromQuery = $query;
    return $this;
  }

  /**
   * Adds a condition on a JSON column to the query the rows come from.
   *
   * An INSERT statement has no WHERE clause of its own, so this method only
   * applies to a query that inserts the result of a select query. Call from()
   * before this method.
   *
   * @param string $field
   *   The JSON column to filter on.
   * @param string $jsonpath
   *   The JSONPath expression (must start with '$').
   * @param string|int|float|array|bool|null $value
   *   The value to compare against. Ignored for unary operators (IS NULL,
   *   IS NOT NULL, HAS KEY).
   * @param string $operator
   *   The comparison operator.
   *
   * @return $this
   *   The called object.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the query has no select query to insert the result of.
   *
   * @see \Drupal\Core\Database\Query\Insert::from()
   * @see \Drupal\Core\Database\Query\QueryConditionTrait::jsonCondition()
   */
  public function jsonCondition(string $field, string $jsonpath, string|int|float|array|bool|null $value = NULL, string $operator = '='): static {
    $this->validateFromQuery('condition');
    $this->fromQuery->condition($this->connection->jsonCondition($field, $jsonpath, $value, $operator));
    return $this;
  }

  /**
   * Inserts a value extracted from a JSON column into a column.
   *
   * The value is taken from the rows the select query returns, so this method
   * only applies to a query that inserts the result of a select query. Call
   * from() before this method.
   *
   * The extracted value is always cast, so that its type is the same on all
   * database backends. Inserting an uncast extraction into a column is not
   * portable.
   *
   * The extraction is added to the select list of the select query under the
   * name of the target column. A call to fields() on this query overrides the
   * columns the values are inserted into, in which case the extraction keeps
   * the position it has in the select list.
   *
   * @param string $field
   *   The column to insert the extracted value into.
   * @param string $json_field
   *   The JSON column to extract the value from.
   * @param string $jsonpath
   *   The JSONPath expression (must start with '$').
   * @param \Drupal\Core\Database\Query\JsonCastType $cast
   *   The type to cast the extracted value to. Defaults to text, which returns
   *   the value unquoted. On MySQL and MariaDB a text cast truncates the value
   *   to 255 characters.
   *
   * @return $this
   *   The called object.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the query has no select query to insert the result of, if the select
   *   query already has an expression named after the target column, or if the
   *   jsonpath is invalid or contains a wildcard. A wildcard matches any
   *   number of values, and an inserted column takes one.
   *
   * @see \Drupal\Core\Database\Query\Insert::from()
   * @see \Drupal\Core\Database\Query\Select::addJsonExpression()
   * @see \Drupal\Core\Database\Query\Update::jsonExpression()
   */
  public function jsonExpression(string $field, string $json_field, string $jsonpath, JsonCastType $cast = JsonCastType::Text): static {
    $this->validateFromQuery('expression');
    $expressions = $this->fromQuery->getExpressions();
    if (isset($expressions[$field])) {
      throw new InvalidQueryException(sprintf('The select query already has an expression named "%s".', $field));
    }
    $expression = $this->connection->jsonExpression($json_field, $jsonpath);
    $this->fromQuery->addExpression($expression->toCastSql($cast), $field);
    return $this;
  }

  /**
   * Checks that the query inserts the result of a select query.
   *
   * @param string $type
   *   The kind of clause that requires the select query, for the message of
   *   the exception.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If from() has not been called.
   */
  protected function validateFromQuery(string $type): void {
    if (empty($this->fromQuery)) {
      throw new InvalidQueryException(sprintf('An insert query only takes a JSON %s when it inserts the result of a select query. Call from() first.', $type));
    }
  }

  /**
   * Executes the insert query.
   *
   * @return int|null|string
   *   The last insert ID of the query, if one exists. If the query was given
   *   multiple sets of values to insert, the return value is undefined. If no
   *   fields are specified, this method will do nothing and return NULL. That
   *   That makes it safe to use in multi-insert loops.
   */
  abstract public function execute();

  /**
   * Implements PHP magic __toString method to convert the query to a string.
   *
   * @return string
   *   The prepared statement.
   */
  public function __toString() {
    // Create a sanitized comment string to prepend to the query.
    $comments = $this->connection->makeComment($this->comments);

    // Default fields are always placed first for consistency.
    $insert_fields = array_merge($this->defaultFields, $this->insertFields);

    if (!empty($this->fromQuery)) {
      return $comments . 'INSERT INTO {' . $this->table . '} (' . implode(', ', $insert_fields) . ') ' . $this->fromQuery;
    }

    // For simplicity, we will use the $placeholders array to inject
    // default keywords even though they are not, strictly speaking,
    // placeholders for prepared statements.
    $placeholders = [];
    $placeholders = array_pad($placeholders, count($this->defaultFields), 'default');
    $placeholders = array_pad($placeholders, count($this->insertFields), '?');

    return $comments . 'INSERT INTO {' . $this->table . '} (' . implode(', ', $insert_fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
  }

  /**
   * Preprocesses and validates the query.
   *
   * @return bool
   *   TRUE if the validation was successful, FALSE if not.
   *
   * @throws \Drupal\Core\Database\Query\FieldsOverlapException
   * @throws \Drupal\Core\Database\Query\NoFieldsException
   */
  protected function preExecute() {
    // Confirm that the user did not try to specify an identical
    // field and default field.
    if (array_intersect($this->insertFields, $this->defaultFields)) {
      throw new FieldsOverlapException('You may not specify the same field to have a value and a schema-default value.');
    }

    if (!empty($this->fromQuery)) {
      // We have to assume that the used aliases match the insert fields.
      // Regular fields are added to the query before expressions, maintain the
      // same order for the insert fields.
      // This behavior can be overridden by calling fields() manually as only
      // the first call to fields() does have an effect.
      $this->fields(array_merge(array_keys($this->fromQuery->getFields()), array_keys($this->fromQuery->getExpressions())));
    }
    else {
      // Don't execute query without fields.
      if (count($this->insertFields) + count($this->defaultFields) == 0) {
        throw new NoFieldsException('There are no fields available to insert with.');
      }
    }

    // If no values have been added, silently ignore this query. This can happen
    // if values are added conditionally, so we don't want to throw an
    // exception.
    if (!isset($this->insertValues[0]) && count($this->insertFields) > 0 && empty($this->fromQuery)) {
      return FALSE;
    }
    return TRUE;
  }

}
