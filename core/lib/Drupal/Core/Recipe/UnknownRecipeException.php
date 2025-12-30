<?php

declare(strict_types=1);

namespace Drupal\Core\Recipe;

/**
 * Exception thrown when recipe is can not be found.
 *
 * @internal
 *   This API is experimental.
 */
final class UnknownRecipeException extends \RuntimeException {

  public function __construct(public readonly string $recipe, public readonly string $searchPath, string $message = "", int $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);
  }

}
