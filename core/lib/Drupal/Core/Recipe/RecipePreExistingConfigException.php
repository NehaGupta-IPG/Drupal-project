<?php

declare(strict_types=1);

namespace Drupal\Core\Recipe;

/**
 * Exception thrown when a recipe has configuration that exists already.
 *
 * @internal
 *   This API is experimental.
 */
class RecipePreExistingConfigException extends \RuntimeException {

  public function __construct(public readonly string $configName, string $message = "", int $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);
  }

}
