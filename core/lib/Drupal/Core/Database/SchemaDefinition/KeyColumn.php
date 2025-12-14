<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Describes a column of a database index or key.
 */
final class KeyColumn implements SchemaDefinitionInterface {

  /**
   * Constructor.
   *
   * @param string $name
   *   The column name.
   * @param int|null $length
   *   (Optional) if set, specifies a prefix of the named column.
   */
  public function __construct(
    public readonly string $name,
    public readonly ?int $length = NULL,
  ) {
  }

  /**
   * Returns the key column as an array item.
   *
   * @return array{0:string|array{0:string, 1:int}}
   *   The key column as an array item.
   */
  public function toArray(): array {
    return $this->length !== NULL ? [[$this->name, $this->length]] : [$this->name];
  }

}
