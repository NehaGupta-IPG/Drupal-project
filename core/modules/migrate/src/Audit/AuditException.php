<?php

namespace Drupal\migrate\Audit;

use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Defines an exception to throw if an error occurs during a migration audit.
 */
class AuditException extends \RuntimeException {

  public function __construct(MigrationInterface $migration, $message, ?\Throwable $previous = NULL) {
    $message = sprintf('Cannot audit migration %s: %s', $migration->id(), $message);
    parent::__construct($message, 0, $previous);
  }

}
