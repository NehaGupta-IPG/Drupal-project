<?php

namespace Drupal\Core\Database\Query;

/**
 * A value object representing an expression with its arguments.
 */
class Expression {

  /**
   * Constructor.
   *
   * @param string $expression
   *   The expression text.
   * @param array<string,string|int|float|bool|array> $arguments
   *   (optional) The expression arguments.
   */
  public function __construct(
    public readonly string $expression,
    public readonly array $arguments = [],
  ) {
  }

}
