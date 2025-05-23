<?php

namespace Drupal\Core\Action;

use Drupal\Core\StringTranslation\TranslatableMarkup;

final readonly class ActionResult {

  /**
   * OperationResult constructor.
   *
   * @param bool $success
   *   Indicates success or failure.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   A message or description.
   * @param ?array $contextValues
   *   Optional extra context.
   */
  public function __construct(
    private bool   $success,
    private TranslatableMarkup $message,
    private ?array $contextValues = []) {
  }

  /**
   * Indicates if the operation was successful.
   *
   * @return bool
   */
  public function isSuccess(): bool {
    return $this->success;
  }

  /**
   * Gets the descriptive message.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The result message.
   */
  public function getMessage(): TranslatableMarkup {
    return $this->message;
  }

  /**
   * Gets any returned context.
   *
   * @return array
   *   The array of values, keyed by context name.
   */
  public function getContextValues(): array {
    return $this->contextValues;
  }

  /**
   * Creates a successful result.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   A message or description.
   * @param ?array $context_values
   *   Optional array of values keyed by context name.
   *
   * @return static
   */
  public static function success(TranslatableMarkup $message, ?array $context_values = []): self {
    return new self(TRUE, $message, $context_values);
  }

  /**
   * Creates a failed result.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   A message or description.
   * @param ?array $context_values
   *   Optional array of values keyed by context name.
   *
   * @return static
   */
  public static function failure(TranslatableMarkup $message, ?array $context_values = []): self {
    return new self(FALSE, $message, $context_values);
  }
}
