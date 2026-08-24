<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

use Drupal\Core\Database\Expression;
use Drupal\Core\Database\Exception\SchemaDefinitionException;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * Represents the query that defines a SQL view.
 *
 * View definitions do not support bound parameters.
 */
final class ViewQuery extends Expression {

  /**
   * Constructor.
   *
   * @param string $expression
   *   The SELECT query defining the view. Never include user input in the
   *   query.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   *   When the expression is empty.
   */
  public function __construct(
    string $expression,
  ) {
    if ($expression === '') {
      throw new SchemaDefinitionException("The 'expression' argument of a ViewQuery cannot be empty");
    }
    parent::__construct($expression);
  }

  /**
   * Creates a view query from a select query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $select
   *   The select query. It must not use bound parameters. Conditions can use
   *   literal values through
   *   \Drupal\Core\Database\Query\SelectInterface::where().
   *
   * @return self
   *   The view query wrapping the select query.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   *   When the select query uses bound parameters.
   */
  public static function fromSelect(SelectInterface $select): self {
    if ($select->getArguments()) {
      throw new SchemaDefinitionException('The query of a SQL view cannot contain bound parameters');
    }
    return new self((string) $select);
  }

}
