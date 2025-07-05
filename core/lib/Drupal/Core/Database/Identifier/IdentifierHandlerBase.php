<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Identifier;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Database\Exception\IdentifierException;

/**
 * Base class to handle identifier value objects.
 *
 * Database drivers should extend this class to implement db-specific
 * limitations and behaviors.
 */
abstract class IdentifierHandlerBase {

  /**
   * A cache of all identifiers handled.
   */
  protected array $identifiers;

  /**
   * Constructor.
   *
   * @param string $tablePrefix
   *   The table prefix to be used by the database connection.
   * @param array{0:string, 1:string} $identifierQuotes
   *   The identifier quote characters for the database type. An array
   *   containing the start and end identifier quote characters for the
   *   database type. The ANSI SQL standard identifier quote character is a
   *   double quotation mark.
   */
  public function __construct(
    public readonly string $tablePrefix,
    public readonly array $identifierQuotes = ['"', '"'],
  ) {
    assert(count($this->identifierQuotes) === 2 && Inspector::assertAllStrings($this->identifierQuotes), __CLASS__ . '::$identifierQuotes must contain 2 string values');
  }

  /**
   * Returns a database identifier value object.
   *
   * @param string|\Drupal\Core\Database\Identifier\Database $identifier
   *   A database name as a string, or a Database value object.
   *
   * @return \Drupal\Core\Database\Identifier\Database
   *   A database identifier value object.
   */
  public function database(string|Database $identifier): Database {
    return $this->getIdentifierValueObject(IdentifierType::Database, $identifier);
  }

  /**
   * Returns a schema identifier value object.
   *
   * @param string|\Drupal\Core\Database\Identifier\Schema $identifier
   *   A schema name as a string, or a Schema value object.
   *
   * @return \Drupal\Core\Database\Identifier\Schema
   *   A schema identifier value object.
   */
  public function schema(string|Schema $identifier): Schema {
    return $this->getIdentifierValueObject(IdentifierType::Schema, $identifier);
  }

  /**
   * Returns a table identifier value object.
   *
   * @param string|\Drupal\Core\Database\Identifier\Table $identifier
   *   A table name as a string, or a Table value object.
   *
   * @return \Drupal\Core\Database\Identifier\Table
   *   A table identifier value object.
   */
  public function table(string|Table $identifier): Table {
    return $this->getIdentifierValueObject(IdentifierType::Table, $identifier);
  }

  /**
   * Returns the value object of an identifier.
   *
   * @param \Drupal\Core\Database\Identifier\IdentifierType $type
   *   The type of identifier.
   * @param string|\Drupal\Core\Database\Identifier\IdentifierBase $identifier
   *   An identifier as a string, or an identifier value object.
   *
   * @return \Drupal\Core\Database\Identifier\IdentifierBase
   *   An identifier value object.
   */
  protected function getIdentifierValueObject(IdentifierType $type, string|IdentifierBase $identifier): IdentifierBase {
    $valueObjectClass = IdentifierType::valueObjectClass($type);

    // If the identifier is a value object already, just return it.
    if ($identifier instanceof $valueObjectClass) {
      return $identifier;
    }

    // Get the value object from the cache if existing, or create a new
    // instance and cache it.
    if ($this->isCached($identifier, $type)) {
      $valueObject = $this->fromCache($identifier, $type);
    }
    else {
      $valueObject = new $valueObjectClass($this, $identifier);
      $this->toCache($identifier, $type, $valueObject);
    }
    return $valueObject;
  }

  /**
   * Adds an identifier value object to the local cache.
   *
   * @param string $identifier
   *   The raw identifier string.
   * @param \Drupal\Core\Database\Identifier\IdentifierType $type
   *   The type of identifier.
   * @param \Drupal\Core\Database\Identifier\IdentifierBase $identifierValueObject
   *   The identifier value object.
   */
  protected function toCache(string $identifier, IdentifierType $type, IdentifierBase $identifierValueObject): void {
    $this->identifiers[$identifier][$type->value] = $identifierValueObject;
  }

  /**
   * Checks if an identifier value object is present in the local cache.
   *
   * @param string $identifier
   *   The raw identifier string.
   * @param \Drupal\Core\Database\Identifier\IdentifierType $type
   *   The type of identifier.
   *
   * @return bool
   *   TRUE if the identifier value object is available in tha local cache,
   *   FALSE otherwise.
   */
  protected function isCached(string $identifier, IdentifierType $type): bool {
    return isset($this->identifiers[$identifier][$type->value]);
  }

  /**
   * Gets an identifier value object from the local cache.
   *
   * @param string $identifier
   *   The raw identifier string.
   * @param \Drupal\Core\Database\Identifier\IdentifierType $type
   *   The type of identifier.
   *
   * @return \Drupal\Core\Database\Identifier\IdentifierBase
   *   The identifier value object.
   */
  protected function fromCache(string $identifier, IdentifierType $type): IdentifierBase {
    return $this->identifiers[$identifier][$type->value];
  }

  /**
   * Returns the maximum length, in bytes, of an identifier type.
   *
   * @return positive-int
   *   The maximum length, in bytes, of an identifier type.
   */
  abstract public function getMaxLength(IdentifierType $type): int;

  /**
   * Returns a string with initial and final quote characters.
   *
   * @param string $value
   *   The input string.
   *
   * @return string
   *   The quoted string.
   */
  public function quote(string $value): string {
    return $this->identifierQuotes[0] . $value . $this->identifierQuotes[1];
  }

  /**
   * Returns a canonicalized and validated identifier string.
   *
   * Standard SQL identifiers designate basic Latin letters, digits 0-9,
   * dollar and underscore as valid characters. Drupal is stricter in the
   * sense that the dollar character is not allowed.
   *
   * @param string $identifier
   *   A raw identifier string. Can include quote characters and any character
   *   in general.
   * @param \Drupal\Core\Database\Identifier\IdentifierType $type
   *   The type of identifier.
   *
   * @return string
   *   A canonicalized and validated identifier string.
   *
   * @throws \Drupal\Core\Database\Exception\IdentifierException
   *   If the identifier is invalid.
   */
  public function canonicalize(string $identifier, IdentifierType $type): string {
    $canonicalName = preg_replace('/[^A-Za-z0-9_]+/', '', $identifier);
    $this->validateCanonicalName($identifier, $canonicalName, $type);
    return $canonicalName;
  }

  /**
   * Validates a canonicalized identifier string.
   *
   * @param string $identifier
   *   A raw identifier string. Can include quote characters and any character
   *   in general.
   * @param string $canonicalName
   *   A canonical identifier string.
   * @param \Drupal\Core\Database\Identifier\IdentifierType $type
   *   The type of identifier.
   *
   * @return true
   *   Upon successful validation.
   *
   * @throws \Drupal\Core\Database\Exception\IdentifierException
   *   If the identifier is invalid.
   */
  protected function validateCanonicalName(string $identifier, string $canonicalName, IdentifierType $type): TRUE {
    $canonicalNameLength = strlen($canonicalName);
    if ($canonicalNameLength > $this->getMaxLength($type) || $canonicalNameLength === 0) {
      throw new IdentifierException(sprintf(
        'The length of the %s identifier \'%s\' once canonicalized to \'%s\' is invalid (maximum allowed: %d)',
        $type->value,
        $identifier,
        $canonicalName,
        $this->getMaxLength($type),
      ));
    }
    return TRUE;
  }

  /**
   * Shortens an identifier's canonical name by adding an hash.
   *
   * This method calculates an hash of the canonical name and then returns a
   * string suitable for machine use. The hash is inserted in the middle of
   * the remaining part of the canonical name once a prefix has been added.
   *
   * @param string $canonicalName
   *   A canonical identifier string.
   * @param array<string,mixed> $info
   *   An associative array of context information.
   * @param \Drupal\Core\Database\Identifier\IdentifierType $type
   *   The type of identifier.
   * @param string $prefix
   *   A prefix that cannot be part of the shortening.
   * @param positive-int $length
   *   The maximum length of the returned string.
   * @param positive-int $hashLength
   *   The length of the hashed part in the returned string.
   *
   * @return string
   *   The shortened string.
   *
   * @throws \Drupal\Core\Database\Exception\IdentifierException
   *   If a shortened string could not be calculated.
   */
  protected function cropByHashing(string $canonicalName, array $info, IdentifierType $type, string $prefix, int $length, int $hashLength): string {
    $allowedLength = $length - strlen($prefix) - $hashLength;
    if ($allowedLength < 4) {
      throw new IdentifierException(sprintf(
        '%s canonical identifier \'%s\' cannot be converted into a machine identifier%s',
        ucfirst($type->value),
        $canonicalName,
        $prefix !== '' ? "; prefix '{$prefix}'" : '',
      ));
    }
    $hash = substr(hash('sha256', $canonicalName), 0, $hashLength);
    $lSize = (int) ($allowedLength / 2);
    $rSize = $allowedLength - $lSize;
    return $prefix . substr($canonicalName, 0, $lSize) . $hash . substr($canonicalName, -$rSize);
  }

  /**
   * Returns the machine accepted string for an identifier.
   *
   * This method converts a canonical identifier in the machine readable
   * version. It could shorten the canonical name or perform other
   * transformation as necessary. The returned value is stored in the
   * identifier's $machineName property.
   *
   * @param string $canonicalName
   *   A canonical identifier string.
   * @param array<string,mixed> $info
   *   An associative array of context information.
   * @param \Drupal\Core\Database\Identifier\IdentifierType $type
   *   The type of identifier.
   *
   * @return string
   *   The machine accepted string for an identifier.
   *
   * @throws \Drupal\Core\Database\Exception\IdentifierException
   *   If a machine string could not be determined.
   */
  public function resolveForMachine(string $canonicalName, array $info, IdentifierType $type): string {
    return match ($type) {
      IdentifierType::Table => $this->resolveTableForMachine($canonicalName, $info),
      default => $canonicalName,
    };
  }

  /**
   * Returns the machine accepted string for a table.
   *
   * @param string $canonicalName
   *   A canonical table name string.
   * @param array<string,mixed> $info
   *   An associative array of context information.
   *
   * @return string
   *   The machine accepted string for a table.
   *
   * @throws \Drupal\Core\Database\Exception\IdentifierException
   *   If a machine string could not be determined.
   */
  protected function resolveTableForMachine(string $canonicalName, array $info): string {
    if (strlen($info['needs_prefix'] ? $this->tablePrefix : '' . $canonicalName) > $this->getMaxLength(IdentifierType::Table)) {
      throw new IdentifierException(sprintf(
        'The machine length of the %s canonicalized identifier \'%s\' once table prefix \'%s\' is added is invalid (maximum allowed: %d)',
        IdentifierType::Table->value,
        $canonicalName,
        $this->tablePrefix,
        $this->getMaxLength(IdentifierType::Table),
      ));
    }
    return $info['needs_prefix'] ? $this->tablePrefix . $canonicalName : $canonicalName;
  }

  /**
   * Parses a raw table identifier string into its components.
   *
   * A raw table identifier may include database and/or schema information in
   * the format [database.][schema.]table and may include quote characters.
   * This method returns the parts that can be used to get a Table identifier
   * value object.
   *
   * @param string $identifier
   *   A raw table identifier string.
   *
   * @return array{database: \Drupal\Core\Database\Identifier\Database|null,schema: \Drupal\Core\Database\Identifier\Schema|null, table: string, needs_prefix: bool}
   *   The parts that can be used to get a Table identifier value object.
   *
   * @throws \Drupal\Core\Database\Exception\IdentifierException
   *   If an error occurred.
   */
  public function parseTableIdentifier(string $identifier): array {
    $parts = explode(".", $identifier);
    [$database, $schema, $table] = match (count($parts)) {
      1 => [NULL, NULL, $parts[0]],
      2 => [NULL, $this->schema($parts[0]), $parts[1]],
      3 => [$this->database($parts[0]), $this->schema($parts[1]), $parts[2]],
      default => throw new IdentifierException(sprintf(
        'The table identifier \'%s\' does not comply with the syntax [database.][schema.]table',
        $identifier,
      )),
    };
    if ($this->tablePrefix !== '') {
      $needsPrefix = count($parts) === 1;
    }
    else {
      $needsPrefix = FALSE;
    }
    return [
      'database' => $database,
      'schema' => $schema,
      'table' => $table,
      'needs_prefix' => $needsPrefix,
    ];
  }

}
