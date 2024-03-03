<?php

declare(strict_types=1);

namespace Drupal\Core;

/**
 * A result type that can either be OkT or ErrorT.
 *
 * A result type is a monadic type holding a returned value or an error code.
 * They provide an elegant way of handling errors, without resorting to
 * exception handling; when a function that may fail returns a result type,
 * the programmer is forced to consider success or failure paths, before
 * getting access to the expected result; this eliminates the possibility of
 * an erroneous programmer assumption.
 *
 * A monad is a structure that combines program fragments (functions) and wraps
 * their return values in a type with additional computation.
 *
 * @template OkT
 * @template ErrorT
 */
final class Result {

  /**
   * Create a new result.
   *
   * @param bool $isOk
   *   TRUE if the result is OkT or FALSE otherwise.
   * @param OkT|ErrorT $value
   *   The value for the result.
   *
   * @phpstan-param ($isOk is true ? OkT : ErrorT) $value
   */
  private function __construct(
    private bool $isOk,
    private $value,
  ) {}

  /**
   * Create a result that resolved to OkT.
   *
   * @template T
   *
   * @param T $value
   *   The value for the successful result.
   *
   * @return self<T, never>
   *   A result in the OkT state.
   */
  public static function ok($value) : self {
    // The indirect assignment and @-var annotation are needed until
    // https://github.com/phpstan/phpstan/issues/6732
    // See https://github.com/phpstan/phpstan/discussions/10667.
    /** @var \Drupal\Core\Result<T, never> $ok */
    $ok = new self(TRUE, $value);
    return $ok;
  }

  /**
   * Create a result that resolved to ErrorT.
   *
   * @template T
   *
   * @param T $value
   *   The value for the error result.
   *
   * @return self<never, T>
   *   A result in the ErrorT state.
   */
  public static function error($value) : self {
    // The indirect assignment and @-var annotation are needed until
    // https://github.com/phpstan/phpstan/issues/6732
    // See https://github.com/phpstan/phpstan/discussions/10667.
    /** @var \Drupal\Core\Result<never, T> $error */
    $error = new self(FALSE, $value);
    return $error;
  }

  /**
   * Check whether the result is OkT.
   *
   * @return bool
   *   Whether the result is OkT.
   *
   * @phpstan-assert-if-true OkT $this->getValue()
   * @phpstan-assert-if-false ErrorT $this->getValue()
   */
  public function isOk() {
    return $this->isOk;
  }

  /**
   * Check whether the result is ErrorT.
   *
   * @return bool
   *   Whether the result is ErrorT.
   *
   * @phpstan-assert-if-true ErrorT $this->getValue()
   * @phpstan-assert-if-false OkT $this->getValue()
   */
  public function isError() {
    return !$this->isOk;
  }

  /**
   * Get the value from the result.
   *
   * @return OkT|ErrorT
   *   The value for the result, the type depends on whether the result is OkT
   *   or ErrorT.
   */
  public function getValue() {
    return $this->value;
  }

}
