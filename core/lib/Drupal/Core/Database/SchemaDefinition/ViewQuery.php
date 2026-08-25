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
   * @param string|\Drupal\Core\Database\Query\SelectInterface $expression
   *   The SELECT query defining the view, either as a string or as a select
   *   query to build it from. A select query must not use bound parameters;
   *   conditions can use literal values through
   *   \Drupal\Core\Database\Query\SelectInterface::where(). Never include
   *   user input in the query.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   *   When the expression is empty, or the select query uses bound
   *   parameters.
   */
  public function __construct(
    string|SelectInterface $expression,
  ) {
    if ($expression instanceof SelectInterface) {
      if ($expression->getArguments()) {
        throw new SchemaDefinitionException('The query of a SQL view cannot contain bound parameters');
      }
      $expression = (string) $expression;
    }
    if ($expression === '') {
      throw new SchemaDefinitionException("The 'expression' argument of a ViewQuery cannot be empty");
    }
    parent::__construct($expression);
  }

}
