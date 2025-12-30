<?php

declare(strict_types=1);

namespace Drupal\Core\Recipe;

use Drupal\Component\Assertion\Inspector;

/**
 * Exception thrown when recipes contain or depend on missing extensions.
 *
 * @internal
 *   This API is experimental.
 */
final class RecipeMissingExtensionsException extends \RuntimeException {

  public function __construct(public readonly array $extensions, string $message = "", int $code = 0, ?\Throwable $previous = NULL) {
    assert(Inspector::assertAllStrings($extensions), 'Extension names must be strings.');
    if (!$message) {
      $sorted = $this->extensions;
      sort($sorted);
      $message = sprintf("The following extensions are missing and are required for this recipe: %s", implode(", ", $sorted));
    }
    parent::__construct($message, $code, $previous);
  }

}
