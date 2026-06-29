<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict;

/**
 * Defines an enum for conflict types in a 3-way comparison.
 *
 * In a 3-way comparison (ours, theirs, base) only two conflict types are
 * possible. Contrib modules can define additional semantic conflict types by
 * providing their own enum.
 */
enum ConflictType: string {

  /* Indicates a change only on the theirs version. */
  case TheirsOnly = 'conflict_theirs_only';

  /* Indicates a different change both on the ours and theirs version. */
  case Both = 'conflict_both';

}
