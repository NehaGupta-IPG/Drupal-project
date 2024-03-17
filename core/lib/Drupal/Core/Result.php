<?php

declare(strict_types=1);

namespace Drupal\Core;

/**
 * A result type that can be used to indicate success or failure.
 *
 * This can be used to indicate whether the operation was a success or failure,
 * as well as providing the successful result or error message, in a single
 * value object.
 *
 * The value contained in the result depends on whether the result is in the
 * Ok or Error state. PHPStan generic annotations can be used to indicate the
 * type of the value contained in the result in the Ok and Error cases.
 *
 * For example, to write a function that processes user input for a number field
 * and returns a result which contains an integer on success but holds an
 * error message on failure you could do the following:
 * ```
 * /**
 *  * @param string $maybeInteger
 *  *   User input that might be a valid integer.
 *  *
 *  * @return \Drupal\Core\Result<int, string>
 *  *   A Result that contains the integer value of the user input on success or
 *  *   an error message for the user in case of error.
 *  * /
 * function convert_to_integer(string $maybeInteger) : Result {
 *   // Check that we're dealing with a numeric type (either integer or float).
 *   if (is_numeric($maybeInteger)) {
 *     // Check that we're not dealing with a float by checking integer
 *     // conversion doesn't truncate anything.
 *     if (((float) (int) $maybeInteger) === ((float) $maybeInteger)) {
 *       return Result::error("The input only supports whole numbers.");
 *     }
 *     // Return the successful integer input.
 *     return Result::ok((int) $maybeInteger);
 *   }
 *   return Result::error("You must input a whole number");
 * }
 *
 * // Validate a set of user input and get a list of successes and failures.
 * $results = array_map(
 *   fn ($maybeInteger) => convert_to_integer($maybeInteger),
 *   ["1", "not-an-int", "3.5", "6", "42"]
 * );
 * ```
 *
 * @template OkT
 *   The type of the value contained in the result in case of success.
 * @template ErrorT
 *   The type of the value contained in the result in case of error.
 */
final class Result {

  /**
   * Create a new result.
   *
   * @param bool $isOk
   *   TRUE if the result is success or FALSE otherwise.
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
   * Create a result that indicated success.
   *
   * @template T
   *
   * @param T $value
   *   The value for the successful result.
   *
   * @return self<T, never>
   *   A result in the success state.
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
   * Create a result that indicates an error.
   *
   * @template T
   *
   * @param T $value
   *   The value for the error result.
   *
   * @return self<never, T>
   *   A result in the error state.
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
   * Check whether the result is successful.
   *
   * @return bool
   *   Whether the result is successful.
   *
   * @phpstan-assert-if-true OkT $this->getValue()
   * @phpstan-assert-if-false ErrorT $this->getValue()
   */
  public function isOk() : bool {
    return $this->isOk;
  }

  /**
   * Check whether the result is an error.
   *
   * @return bool
   *   Whether the result is an error.
   *
   * @phpstan-assert-if-true ErrorT $this->getValue()
   * @phpstan-assert-if-false OkT $this->getValue()
   */
  public function isError() : bool {
    return !$this->isOk;
  }

  /**
   * Get the value from the result.
   *
   * @return OkT|ErrorT
   *   The value contained in the result. Will be of generic type OkT in case
   *   the result is a success and type ErrorT in case the result is an error.
   */
  public function getValue() {
    return $this->value;
  }

}
