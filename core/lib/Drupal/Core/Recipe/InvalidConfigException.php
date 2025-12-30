<?php

declare(strict_types=1);

namespace Drupal\Core\Recipe;

use Drupal\Core\Config\Schema\Mapping;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Thrown if config created or changed by a recipe fails validation.
 *
 * @internal
 *   This API is experimental.
 */
final class InvalidConfigException extends \RuntimeException {

  public function __construct(
    public readonly ConstraintViolationList $violations,
    public readonly Mapping $data,
    string $message = '',
    int $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message ?: $this->formatMessage(), $code, $previous);
  }

  /**
   * Formats the constraint violation list as a human-readable message.
   *
   * @return string
   *   The formatted message.
   */
  private function formatMessage(): string {
    $lines = [
      sprintf('There were validation errors in %s:', $this->data->getName()),
    ];
    /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
    foreach ($this->violations as $violation) {
      $lines[] = sprintf('- %s: %s', $violation->getPropertyPath(), $violation->getMessage());
    }
    return implode("\n", $lines);
  }

}
