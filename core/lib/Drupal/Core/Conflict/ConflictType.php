<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict;

/**
 * Defines an enum for conflict types.
 */
enum ConflictType: string implements ConflictTypeInterface {

  /* Indicates a change only on the remote version. */
  case Remote = 'conflict_remote';

  /* Indicates a different change both on the local and remote version. */
  case LocalRemote = 'conflict_local_remote';

}
