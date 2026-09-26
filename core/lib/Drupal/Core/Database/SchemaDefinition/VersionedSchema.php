<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Database\Exception\SchemaDefinitionException;

/**
 * Describes a multi-version schema as a collection of schema objects.
 */
final class VersionedSchema implements SchemaDefinitionInterface {

  /**
   * Constructor.
   *
   * @param SchemaDefinitionType $type
   *   The type of Drupal feature providing the schema.
   * @param string $name
   *   The schema name.
   * @param list<Schema> $schemaVersions
   *   A list of different versions of the same Schema object.
   */
  public function __construct(
    public readonly SchemaDefinitionType $type,
    public readonly string $name,
    public readonly array $schemaVersions,
  ) {
    $this->validate();
  }

  /**
   * Validates the properties of the value object.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   */
  private function validate(): void {
    if (!Inspector::assertAllObjects($this->schemaVersions, Schema::class)) {
      throw new SchemaDefinitionException("All members of the 'schemaVersions' argument of the '{$this->name}' VersionedSchema must be Schema objects");
    }
    foreach ($this->schemaVersions as $schema) {
      if ($schema->type !== $this->type) {
        throw new SchemaDefinitionException("A schema does not match the '{$this->type->value}' type of the '{$this->name}' VersionedSchema");
      }
      if ($schema->name !== $this->name) {
        throw new SchemaDefinitionException("The '{$schema->name}' schema does not match the name of the '{$this->name}' VersionedSchema");
      }
      if ($schema->version === NULL) {
        throw new SchemaDefinitionException("At least one of the schema versions of '{$this->name}' does not indicate the version level");
      }
    }
    $schemaVersions = $this->schemaVersions();
    if (count($schemaVersions) !== count(array_unique($schemaVersions))) {
      throw new SchemaDefinitionException("Duplicate versions for '{$this->name}' VersionedSchema: " . implode(", ", $schemaVersions));
    }
  }

  /**
   * Returns the list of schema versions defined.
   *
   * @return list<int>
   *   The list of schema versions defined, sorted by version level.
   */
  public function schemaVersions(): array {
    $list = array_map(
      fn(Schema $schema): int => $schema->version,
      $this->schemaVersions,
    );
    sort($list);
    return $list;
  }

  /**
   * Gets a specific schema version.
   *
   * @param int $version
   *   The version of the Schema object to return.
   *
   * @return Schema
   *   The Schema object.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   *   When the requested schema is missing.
   */
  public function getSchemaVersionDefinition(int $version): Schema {
    $ret = array_find(
      $this->schemaVersions,
      function (Schema $schema) use ($version): bool {
        return $schema->version === $version;
      },
    );
    if (!$ret) {
      throw new SchemaDefinitionException("Schema {$this->name} does not have a version {$version}");
    }
    return $ret;
  }

  /**
   * Gets the latest schema version.
   *
   * @return Schema
   *   The Schema object.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   *   When the requested schema is missing.
   */
  public function getLatestSchemaVersionDefinition(): Schema {
    return $this->getSchemaVersionDefinition(max($this->schemaVersions()));
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    throw new SchemaDefinitionException("The '{$this->name}' VersionedSchema cannot be converted to legacy array-based specifications");
  }

}
