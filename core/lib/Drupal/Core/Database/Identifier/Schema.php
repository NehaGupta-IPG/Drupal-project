<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Identifier;

/**
 * Handles a schema identifier.
 *
 * When using full notation, a table can be identified as
 * [database.][schema.]table.
 */
final class Schema extends IdentifierBase {

  public function __construct(
    IdentifierHandlerBase $identifierHandler,
    string $identifier,
  ) {
    $canonicalName = $identifierHandler->canonicalize($identifier, IdentifierType::Schema);
    $machineName = $identifierHandler->resolveForMachine($canonicalName, [], IdentifierType::Schema);
    parent::__construct($identifier, $canonicalName, $machineName, $identifierHandler->quote($machineName));
  }

}
