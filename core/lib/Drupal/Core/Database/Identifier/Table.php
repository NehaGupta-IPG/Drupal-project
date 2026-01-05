<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Identifier;

/**
 * Handles a table identifier.
 *
 * When using full notation, a table can be identified as
 * [database.][schema.]table.
 */
final class Table extends IdentifierBase {

  /**
   * The database identifier, if specified.
   */
  public readonly ?Database $database;

  /**
   * The schema identifier, if specified.
   */
  public readonly ?Schema $schema;

  /**
   * Whether the table requires to be prefixed.
   */
  public readonly bool $needsPrefix;

  public function __construct(
    IdentifierHandlerBase $identifierHandler,
    string $identifier,
  ) {
    $parts = $identifierHandler->parseTableIdentifier($identifier);

    $canonicalName = $identifierHandler->canonicalize($parts['table'], IdentifierType::Table);
    $machineName = $identifierHandler->resolveForMachine($canonicalName, $parts, IdentifierType::Table);
    parent::__construct($identifier, $canonicalName, $machineName, $identifierHandler->quote($machineName));

    $this->database = $parts['database'];
    $this->schema = $parts['schema'];
    $this->needsPrefix = $parts['needs_prefix'];
  }

  /**
   * {@inheritdoc}
   */
  public function canonical(): string {
    $ret = isset($this->database) ? $this->database->canonicalName . '.' : '';
    $ret .= isset($this->schema) ? $this->schema->canonicalName . '.' : '';
    $ret .= $this->canonicalName;
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function forMachine(): string {
    $ret = isset($this->database) ? $this->database->quotedMachineName . '.' : '';
    $ret .= isset($this->schema) ? $this->schema->quotedMachineName . '.' : '';
    $ret .= $this->quotedMachineName;
    return $ret;
  }

}
