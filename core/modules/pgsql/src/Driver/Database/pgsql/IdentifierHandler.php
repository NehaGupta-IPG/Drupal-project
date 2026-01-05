<?php

declare(strict_types=1);

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Database\Exception\IdentifierException;
use Drupal\Core\Database\Identifier\IdentifierHandlerBase;
use Drupal\Core\Database\Identifier\IdentifierType;
use Drupal\Core\Database\Identifier\Schema;

/**
 * PostgreSql implementation of the identifier handler.
 */
class IdentifierHandler extends IdentifierHandlerBase {

  /**
   * The default schema identifier, if specified.
   */
  public readonly ?Schema $defaultSchema;

  /**
   * Constructor.
   *
   * @param string $tablePrefix
   *   The table prefix to be used by the database connection.
   * @param string $defaultSchema
   *   The default schema to use for database operations. If not specified,
   *   it's assumed to use the 'public' schema.
   * @param array{0:string, 1:string} $identifierQuotes
   *   The identifier quote characters.
   */
  public function __construct(
    string $tablePrefix,
    string $defaultSchema = '',
    array $identifierQuotes = ['"', '"'],
  ) {
    parent::__construct($tablePrefix, $identifierQuotes);
    if ($defaultSchema !== '' && $defaultSchema !== 'public') {
      $this->defaultSchema = $this->schema($defaultSchema);
    }
    else {
      $this->defaultSchema = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxLength(IdentifierType $type): int {
    // @see https://www.postgresql.org/docs/current/limits.html
    return 63;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateCanonicalName(string $identifier, string $canonicalName, IdentifierType $type): true {
    return match ($type) {
      IdentifierType::Table => TRUE,
      default => parent::validateCanonicalName($identifier, $canonicalName, $type),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveTableForMachine(string $canonicalName, array $info): string {
    if ($info['schema'] && ($info['schema_default_added'] ?? FALSE) === FALSE) {
      // We are processing a fully qualified table identifier, canonical name
      // should not be processed, just checked it does not exceed max length.
      if (strlen($canonicalName) > $this->getMaxLength(IdentifierType::Table)) {
        throw new IdentifierException(sprintf(
          'Table identifier \'%s\' exceeds maximum allowed length (%d)',
          $canonicalName,
          $this->getMaxLength(IdentifierType::Table),
        ));
      }
      return $canonicalName;
    }

    $prefix = $info['needs_prefix'] ? $this->tablePrefix : '';
    if (strlen($prefix . $canonicalName) > $this->getMaxLength(IdentifierType::Table)) {
      // We shorten too long table names.
      return $this->cropByHashing($canonicalName, $info, IdentifierType::Table, $prefix, $this->getMaxLength(IdentifierType::Table), 10);
    }
    return $prefix . $canonicalName;
  }

  /**
   * {@inheritdoc}
   */
  public function parseTableIdentifier(string $identifier): array {
    $parts = parent::parseTableIdentifier($identifier);
    if (isset($this->defaultSchema) && $parts['schema'] === NULL) {
      // When a non-public schema is defined for the connection, machine names
      // for table identifiers should be in the "schema"."table" format.
      $parts['schema'] = $this->defaultSchema;
      $parts['schema_default_added'] = TRUE;
    }
    return $parts;
  }

}
