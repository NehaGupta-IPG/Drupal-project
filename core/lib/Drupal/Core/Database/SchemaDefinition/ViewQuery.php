<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

use Drupal\Core\Database\Expression;
use Drupal\Core\Database\Exception\SchemaDefinitionException;

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

}
