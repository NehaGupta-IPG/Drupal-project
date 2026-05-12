<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict;

/**
 * Defines an interface for conflict type enums.
 *
 * This is an interface rather than a fixed enum so that contrib modules can
 * define their own conflict type enums for domain-specific scenarios while
 * remaining compatible with the conflict resolution API. Core provides
 * ConflictType as the default implementation covering standard 3-way
 * comparison conflicts.
 */
interface ConflictTypeInterface {

}
