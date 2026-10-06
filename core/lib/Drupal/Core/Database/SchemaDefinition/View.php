<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

use Drupal\Core\Database\Exception\SchemaDefinitionException;

/**
 * Describes a SQL view.
 */
final class View {

  /**
   * Constructor.
   *
   * @param non-empty-string $name
   *   The view name.
   * @param ViewQuery $query
   *   The SELECT query defining the view.
   * @param ?string $description
   *   (optional) A plain-text description that documents the view definition.
   *   It is not stored in the database.
   */
  public function __construct(
    public readonly string $name,
    public readonly ViewQuery $query,
    public readonly ?string $description = NULL,
  ) {
    $this->validate();
  }

  /**
   * Checks that the view name is not empty.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   *   When the view name is empty.
   */
  private function validate(): void {
    if ($this->name === '') {
      throw new SchemaDefinitionException("The 'name' argument of a View cannot be empty");
    }
  }

}
