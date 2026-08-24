<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Interface for database index classes.
 *
 * Concrete subclasses describe the index technology to be used. Not every
 * database engine supports every index technology; each database driver
 * declares the index definition classes it supports, and skips the ones it
 * does not. Use AlternativeIndexes to specify a fallback index to be created
 * when the preferred index technology is not supported.
 */
interface IndexInterface {
}
