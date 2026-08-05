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
 *     new \Drupal\pgsql\Driver\Database\pgsql\GinIndex(name: 'ages', columns: ['age']),
 *     new Index(name: 'ages', columns: ['age']),
 *   ],
 * ),
 * @endcode
 */
final class AlternativeIndexes implements SchemaDefinitionInterface {

  /**
   * Constructor.
   *
   * @param string $name
   *   The index name.
   * @param list<IndexBase> $indexes
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
    if (!Inspector::assertAllObjects($this->indexes, IndexBase::class)) {
      throw new SchemaDefinitionException("All members of the 'indexes' argument of the '{$this->name}' AlternativeIndexes must be IndexBase objects");
    }
    foreach ($this->indexes as $index) {
      if ($index->name !== $this->name) {
        throw new SchemaDefinitionException("The '{$index->name}' index does not match the name of the '{$this->name}' AlternativeIndexes");
      }
    }

  }

  /**
   * Returns the first index that is an instance of the given classes.
   *
   * @param list<class-string<IndexBase>> $classes
   *   The index definition classes to match, normally the classes supported
   *   by a database driver.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\IndexBase|null
   *   The most preferred index matching one of the given classes, or NULL if
   *   none matches.
   */
  public function firstOfClasses(array $classes): ?IndexBase {
    foreach ($this->indexes as $index) {
      if (in_array($index::class, $classes, TRUE)) {
        return $index;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * The legacy array-based specifications cannot describe alternative
   * indexes; the first regular Index is used, if defined.
   *
   * @throws \LogicException
   *   When no regular Index alternative is defined.
   */
  public function toArray(): array {
    $index = $this->firstOfClasses([Index::class]);
    if ($index === NULL) {
      throw new \LogicException("The '{$this->name}' AlternativeIndexes cannot be converted to legacy array-based specifications");
    }
    return $index->toArray();
  }

}
