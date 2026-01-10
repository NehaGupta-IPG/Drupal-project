<?php

declare(strict_types = 1);

namespace Drupal\Core\Hook\OrderOperation;

/**
 * Moves one listener to the start or end of the list.
 *
 * @internal
 */
class FirstOrLast extends OrderOperation {

  public function __construct(
    protected readonly string $identifier,
    protected readonly bool $isLast,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function identify(): string {
    return $this->identifier;
  }

  /**
   * {@inheritdoc}
   */
  public function apply(array &$identifiers, array $module_finder): void {
    $index = array_search($this->identifier, $identifiers);
    if ($index === FALSE) {
      // The element does not exist.
      return;
    }
    unset($identifiers[$index]);
    if ($this->isLast) {
      $identifiers[] = $this->identifier;
    }
    else {
      $identifiers = [$this->identifier, ...$identifiers];
    }
    $identifiers = array_values($identifiers);
  }

}
