<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Database\Exception\SchemaDefinitionException;

/**
 * Describes alternative indexes, in order of preference.
 *
 * Some index technologies, like GIN and GiST, are not supported by every
 * database engine. This class lists alternative definitions for the same
 * logical index; the database driver creates the first index it supports and
 * ignores the others. For example, a driver that does not support GIN indexes
 * falls back to a regular index:
 * @code
 * new AlternativeIndexes(
 *   name: 'ages',
 *   indexes: [
 *     new GinIndex(name: 'ages', columns: ['age']),
 *     new Index(name: 'ages', columns: ['age']),
 *   ],
 * ),
 * @endcode
 */
final class AlternativeIndexes implements SchemaDefinitionInterface, IndexInterface {

  /**
   * Constructor.
   *
   * @param string $name
   *   The index name.
   * @param list<IndexInterface> $indexes
   *   The alternative index definitions, in order of preference. Each index
   *   must have the same name as this object.
   */
  public function __construct(
    public readonly string $name,
    public readonly array $indexes,
  ) {
    $this->validate();
  }

  /**
   * Validates the properties of the value object.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   */
  private function validate(): void {

    if ($this->indexes === []) {
      throw new SchemaDefinitionException("The 'indexes' argument of the '{$this->name}' AlternativeIndexes must not be empty");
    }
    if (!Inspector::assertAllObjects($this->indexes, IndexInterface::class)) {
      throw new SchemaDefinitionException("All members of the 'indexes' argument of the '{$this->name}' AlternativeIndexes must be IndexInterface objects");
    }
    foreach ($this->indexes as $index) {
      if ($index->name !== $this->name) {
        throw new SchemaDefinitionException("The '{$index->name}' index does not match the name of the '{$this->name}' AlternativeIndexes");
      }
    }

  }

  /**
   * {@inheritdoc}
   *
   * The legacy array-based specifications cannot describe alternative
   * indexes; the first regular Index is used, if defined.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   *   When no regular Index alternative is defined.
   */
  public function toArray(): array {
    foreach ($this->indexes as $index) {
      if ($index instanceof Index) {
        $tmp = $index;
        break;
      }
    }

    if (!isset($tmp)) {
      throw new SchemaDefinitionException("The '{$this->name}' AlternativeIndexes cannot be converted to legacy array-based specifications, no regular Index specified");
    }

    return $tmp->toArray();
  }

}
