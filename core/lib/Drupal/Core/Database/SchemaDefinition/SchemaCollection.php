<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Database\Exception\SchemaDefinitionException;

/**
 * Describes a collection of schema objects.
 */
final class SchemaCollection implements SchemaDefinitionInterface {

  /**
   * The schema collection name.
   */
  public readonly string $name;

  /**
   * Constructor.
   *
   * @param list<Schema> $schemas
   *   A list of Schema objects.
   */
  public function __construct(
    public readonly array $schemas,
  ) {
    $this->name = 'schema-collection';
    $this->validate();
  }

  /**
   * Validates the properties of the value object.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   */
  private function validate(): void {
    if ($this->schemas && !Inspector::assertAllObjects($this->schemas, Schema::class)) {
      throw new SchemaDefinitionException("All members of the 'schemas' argument of a SchemaCollection must be Schema objects");
    }
    $schemaNames = $this->schemaNames();
    if (count($schemaNames) !== count(array_unique($schemaNames))) {
      throw new SchemaDefinitionException("Duplicate schema for the schema collection: " . implode(', ', $schemaNames));
    }
  }

  /**
   * Returns the names of the schemas.
   *
   * @return list<string>
   *   The names of the schemas.
   */
  public function schemaNames(): array {
    return array_map(
      fn(Schema $schema): string => $schema->name,
      $this->schemas,
    );
  }

  /**
   * Gets a specific schema definition.
   *
   * @param string $name
   *   The name of the Schema object to return.
   *
   * @return Schema
   *   The Schema object.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   *   When the requested schema is missing.
   */
  public function getSchemaDefinition(string $name): Schema {
    $ret = array_find(
      $this->schemas,
      function (Schema $schema) use ($name): bool {
        return $schema->name === $name;
      },
    );
    if (!$ret) {
      throw new SchemaDefinitionException("Schema collection does not define a schema named {$name}");
    }
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    throw new \LogicException(__CLASS__ . ' objects cannot be converted to array');
  }

}
