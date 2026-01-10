<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Checkpoint;

/**
 * A value object to store information about a checkpoint.
 *
 * @internal
 *   This API is experimental.
 */
final class Checkpoint {

  public function __construct(
    public readonly string $id,
    public readonly \Stringable|string $label,
    public readonly int $timestamp,
    public readonly ?string $parent,
  ) {
  }

}
