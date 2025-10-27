<?php

namespace Drupal\Core\Conflict;

/**
 * Defines an enum for conflict types.
 */
enum ConflictTypes: string implements ConflictTypeInterface {

  /* Indicates a change only on the remote version. */
  case ConflictTypeRemote = 'conflict_remote';

  /* Indicates a different change both on the local and remote version. */
  case ConflictTypeLocalRemote = 'conflict_local_remote';

}
