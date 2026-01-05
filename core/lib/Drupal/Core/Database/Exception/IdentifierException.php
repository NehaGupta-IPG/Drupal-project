<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Exception;

use Drupal\Core\Database\DatabaseException;

/**
 * Exception thrown by the identifier handling API.
 */
class IdentifierException extends \RuntimeException implements DatabaseException {
}
