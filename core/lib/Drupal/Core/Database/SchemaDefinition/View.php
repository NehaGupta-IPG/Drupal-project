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
   * @param string $name
   *   The view name.
   * @param ViewQuery $query
   *   The SELECT query defining the view.
   * @param ?string $description
   *   (Optional) A plain-text description of the view. This documents the
   *   definition and is not stored in the database.
   */
  public function __construct(
    public readonly string $name,
    public readonly ViewQuery $query,
    public readonly ?string $description = NULL,
  ) {
    $this->validate();
  }

  /**
   * Validates the properties of the value object.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   */
  private function validate(): void {

    if ($this->name === '') {
      throw new SchemaDefinitionException("The 'name' argument of a View cannot be empty");
    }

  }

}
