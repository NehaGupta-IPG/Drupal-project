<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Transaction;

/**
 * A value object for items on the transaction stack.
 */
final class StackItem {

  public function __construct(
    public readonly string $name,
    public readonly StackItemType $type,
  ) {
  }

}
