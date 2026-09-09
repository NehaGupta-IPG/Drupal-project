<?php

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\Query\Upsert as QueryUpsert;

/**
 * MySQL implementation of \Drupal\Core\Database\Query\Upsert.
 */
class Upsert extends QueryUpsert {

  /**
   * Whether the update condition fits in a single statement.
   *
   * @var bool|null
   */
  private ?bool $atomicUpdateCondition = NULL;

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    // Create a sanitized comment string to prepend to the query.
    $comments = $this->connection->makeComment($this->comments);

    // Default fields are always placed first for consistency.
    $insert_fields = array_merge($this->defaultFields, $this->insertFields);
    $insert_fields = array_combine($insert_fields, $insert_fields);
    $insert_fields = array_map(function ($field) {
      return $this->connection->escapeField($field);
    }, $insert_fields);

    // Escape column names of update values.
    $update_expressions = [];
    foreach ($this->updateExpressions as $column => $expression) {
      $update_expressions[$this->connection->escapeField($column)] = $expression->expression;
    }

    // Updating the unique / primary key fields is not necessary.
    $update_fields = $insert_fields;
    foreach ($this->key as $key) {
      unset($update_fields[$key]);
    }

    // Whether the update condition is done by this statement, or by the
    // second statement of ::execute().
    $conditional_clause = $this->updateCondition && $this->hasAtomicUpdateCondition();

    $query = $comments . 'INSERT ';

    // Ignore a duplicate key when this statement does not handle it itself:
    // with no column to update the existing record is kept as it is, and an
    // update condition that is not in the clause below is a separate
    // statement in ::execute().
    if (empty($update_fields) || ($this->updateCondition && !$conditional_clause)) {
      $query .= 'IGNORE ';
    }

    $query .= 'INTO {' . $this->table . '} (' . implode(', ', $insert_fields) . ') VALUES ';
    $values = $this->getInsertPlaceholderFragment($this->insertValues, $this->defaultFields);
    $query .= implode(', ', $values);

    if (empty($update_fields) || ($this->updateCondition && !$conditional_clause)) {
      return $query;
    }

    if ($conditional_clause) {
      // The assignments are evaluated in order against the record as it is
      // being updated, so a column the condition reads is assigned last.
      $update_fields = $this->orderUpdateFields($update_fields);
    }

    $update = [];
    foreach ($update_fields as $field) {
      $value = $update_expressions[$field] ?? "VALUES($field)";
      // MySQL and MariaDB have no condition clause for the update, so the
      // condition gates each column assignment separately.
      $update[] = $conditional_clause
        ? "$field = CASE WHEN {$this->updateCondition->expression} THEN $value ELSE $field END"
        : "$field = $value";
    }
    $query .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $update);

    if ($conditional_clause) {
      // Report the outcome by comparing the resulting record with the values
      // that were written for it, since the affected row count cannot.
      $query .= ' RETURNING ' . $this->updateAppliedExpression();
    }

    return $query;
  }

  /**
   * Determines whether the update condition fits in a single statement.
   *
   * MariaDB supports RETURNING on an upsert, which reports the record the
   * statement leaves behind. Comparing it with the values that were written
   * tells whether the update was done, where the affected row count cannot:
   * with CLIENT_FOUND_ROWS, which the driver connects with, an existing
   * record left at its current values counts as one affected row, the same as
   * an inserted record. MySQL has no RETURNING clause at any version.
   *
   * @return bool
   *   TRUE if the condition and its outcome fit in one statement.
   */
  protected function hasAtomicUpdateCondition(): bool {
    // Columns inserted with their schema default hold no value to compare, so
    // at least one column must carry one. The columns of a query no longer
    // change once it is executed, so the answer is kept.
    return $this->atomicUpdateCondition ??= $this->connection->isMariaDb()
      && (bool) array_diff($this->insertFields, $this->key);
  }

  /**
   * Orders the updated columns for the condition of a single statement.
   *
   * The assignments of the update clause are evaluated in order against the
   * record as it is being updated. A column the condition reads is therefore
   * assigned after the columns it does not read, so that every assignment
   * sees the record as it was before the update.
   *
   * @param array $update_fields
   *   The columns to update, escaped, keyed by column name.
   *
   * @return array
   *   The same columns, the one the condition reads last.
   */
  protected function orderUpdateFields(array $update_fields): array {
    $condition = $this->updateCondition->expression;
    // Both the qualified form the condition is written in and the escaped form
    // it compiles to are recognized.
    $reads = fn (string $column): bool => str_contains($condition, "[$column]")
      || str_contains($condition, $this->connection->escapeField($column));
    uksort($update_fields, fn ($a, $b) => (int) $reads($a) <=> (int) $reads($b));

    return $update_fields;
  }

  /**
   * Builds the expression that reports whether the update was done.
   *
   * @return string
   *   An expression comparing the resulting record with the values written for
   *   it, column by column. The comparison is NULL safe, so a column written
   *   with NULL compares as written.
   */
  protected function updateAppliedExpression(): string {
    $comparisons = [];
    $placeholder = 0;
    foreach ($this->insertFields as $field) {
      // The columns of the key are never updated, and hold the values that
      // were written by definition.
      if (!in_array($field, $this->key, TRUE)) {
        $comparisons[] = $this->connection->escapeField($field) . ' <=> :db_upsert_written_' . $placeholder++;
      }
    }

    return '(' . implode(' AND ', $comparisons) . ')';
  }

  /**
   * Returns the values written for the columns of ::updateAppliedExpression().
   *
   * @return array
   *   The values that were written, keyed by placeholder name.
   */
  protected function updateAppliedArguments(): array {
    $insert_values = reset($this->insertValues) ?: [];
    $arguments = [];
    $placeholder = 0;
    foreach (array_values($this->insertFields) as $index => $field) {
      if (!in_array($field, $this->key, TRUE)) {
        $arguments[':db_upsert_written_' . $placeholder++] = $insert_values[$index] ?? NULL;
      }
    }

    return $arguments;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    if (!$this->updateCondition) {
      return parent::execute();
    }
    if (!$this->preExecute()) {
      return NULL;
    }

    $values = [];
    $max_placeholder = 0;
    foreach ($this->insertValues as $insert_values) {
      foreach ($insert_values as $value) {
        $values[':db_insert_placeholder_' . $max_placeholder++] = $value;
      }
    }

    // On MariaDB the whole upsert is one statement that reports its own
    // outcome.
    if ($this->hasAtomicUpdateCondition()) {
      $query = (string) $this;
      $arguments = $values + $this->updateAppliedArguments() + $this->updateCondition->arguments;

      // Re-initialize the values array so that we can re-use this query.
      $this->insertValues = [];

      return (int) (bool) $this->connection->query($query, $arguments, $this->queryOptions)->fetchField();
    }

    // MySQL has no condition clause for the update part of an upsert, and no
    // RETURNING to report the outcome with. Gating each column assignment of
    // the clause with a CASE expression cannot report it either: with
    // CLIENT_FOUND_ROWS, which the driver connects with, an existing record
    // left at its current values counts as one affected row, the same as an
    // inserted record.
    //
    // The insert and the conditional update are therefore done as two
    // statements. Both are atomic and exclude each other: the insert only adds
    // a record while none exists, and the update only writes while the
    // condition holds for the record that does exist. A concurrent request can
    // make either of them do nothing, never both do something.
    $affected = $this->runStatement((string) $this, $values);

    if (!$affected) {
      $affected = $this->runStatement($this->updateStatement(), $values + $this->updateCondition->arguments);
    }

    // Re-initialize the values array so that we can re-use this query.
    $this->insertValues = [];

    return $affected;
  }

  /**
   * Executes one statement of a conditional upsert.
   *
   * @param string $query
   *   The statement to execute.
   * @param array $arguments
   *   The statement arguments, keyed by placeholder name.
   *
   * @return int
   *   The number of rows affected by the statement.
   */
  protected function runStatement(string $query, array $arguments): int {
    $stmt = $this->connection->prepareStatement($query, $this->queryOptions, TRUE);
    try {
      $stmt->execute($arguments, $this->queryOptions);
      return $stmt->rowCount();
    }
    catch (\Exception $e) {
      $this->connection->exceptionHandler()->handleExecutionException($e, $stmt, $arguments, $this->queryOptions);
    }
  }

  /**
   * Builds the conditional update statement of a conditional upsert.
   *
   * The statement reuses the insert placeholders, so it takes the same
   * arguments as the insert statement, plus the condition arguments.
   *
   * @return string
   *   The update statement.
   */
  protected function updateStatement(): string {
    $update = [];
    $conditions = [];

    // Default fields are always placed first for consistency, as they are in
    // the insert statement, and they hold no placeholder.
    foreach ($this->defaultFields as $field) {
      $update[] = $this->connection->escapeField($field) . ' = DEFAULT';
    }
    $max_placeholder = 0;
    foreach ($this->insertFields as $field) {
      $assignment = $this->connection->escapeField($field) . ' = :db_insert_placeholder_' . $max_placeholder++;
      // The record to update is the one the insert conflicted with.
      if (in_array($field, $this->key, TRUE)) {
        $conditions[] = $assignment;
      }
      else {
        $update[] = $assignment;
      }
    }
    $conditions[] = '(' . $this->updateCondition->expression . ')';

    return $this->connection->makeComment($this->comments)
      . 'UPDATE {' . $this->table . '} SET ' . implode(', ', $update)
      . ' WHERE ' . implode(' AND ', $conditions);
  }

}
