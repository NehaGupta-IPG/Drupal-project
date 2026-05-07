<?php

declare(strict_types=1);

namespace Drupal\Component\PhpStorage;

/**
 * Extends PHP storage with advisory lock primitives.
 *
 * Implementations must guarantee that concurrent calls to save() for the same
 * name are serialized: at most one write proceeds at a time, and any competing
 * caller receives FALSE without blocking.
 */
interface LockingPhpStorageInterface extends PhpStorageInterface {
}
