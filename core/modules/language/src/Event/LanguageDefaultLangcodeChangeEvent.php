<?php

declare(strict_types=1);

namespace Drupal\language\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event fired when the site's default language changes.
 *
 * Subscribers add their own batch operations - for example, translating
 * relabelled configuration - to run as part of the same batch that
 * reconciles configuration langcode with the new default.
 *
 * @see \Drupal\language\LanguageEvents::DEFAULT_LANGCODE_CHANGE
 */
final class LanguageDefaultLangcodeChangeEvent extends Event {

  /**
   * The batch operations gathered so far.
   *
   * @var array
   */
  private array $operations = [];

  /**
   * Constructs a LanguageDefaultLangcodeChangeEvent.
   *
   * @param string $oldLangcode
   *   The previous default langcode.
   * @param string $newLangcode
   *   The new default langcode.
   */
  public function __construct(
    public readonly string $oldLangcode,
    public readonly string $newLangcode,
  ) {}

  /**
   * Adds a batch operation to run as part of the same batch.
   *
   * @param callable|string $callback
   *   The batch operation callback.
   * @param array $arguments
   *   Arguments to pass to the callback, before the $context argument
   *   Batch API appends automatically.
   */
  public function addOperation(callable|string $callback, array $arguments = []): void {
    $this->operations[] = [$callback, $arguments];
  }

  /**
   * Gets the batch operations gathered so far.
   *
   * @return array
   *   An array of Batch API operations.
   */
  public function getOperations(): array {
    return $this->operations;
  }

}
