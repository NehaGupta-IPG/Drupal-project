<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict;

/**
 * Defines an enum for conflict types in a 3-way comparison.
 *
 * In a 3-way comparison (local, remote, base) only two conflict types are
 * possible. Contrib modules can define additional semantic conflict types by
 * implementing ConflictTypeInterface.
 */
enum ConflictType: string implements ConflictTypeInterface {

  /* Indicates a change only on the remote version. */
  case Remote = 'conflict_remote';

  /* Indicates a different change both on the local and remote version. */
  case LocalRemote = 'conflict_local_remote';

}
