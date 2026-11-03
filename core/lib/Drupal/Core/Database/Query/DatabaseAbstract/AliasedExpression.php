<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

use Drupal\Core\Database\Expression;

/**
 * A value object representing an expression with a SQL alias.
 */
class AliasedExpression extends Expression {

  /**
   * Constructor.
   *
   * @param string $expression
   *   The expression text. Never include user input in the expression text;
   *   pass it through $arguments placeholders instead.
   * @param array<string,string|int|float|bool> $arguments
   *   (optional) The expression arguments, keyed by placeholder name.
   * @param ?string $alias
   *   (optional) An alias for this expression.
   */
  public function __construct(
    string $expression,
    array $arguments = [],
    public readonly ?string $alias = NULL,
  ) {
    parent::__construct($expression, $arguments);
  }

}
