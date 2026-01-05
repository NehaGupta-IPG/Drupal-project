<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Identifier;

/**
 * Enumeration of database identifier types.
 */
enum IdentifierType: string {

  case Database = 'database';
  case Schema = 'schema';
  case Table = 'table';

  /**
   * Returns the value object class for an identifier type.
   *
   * @param Drupal\Core\Database\Identifier\IdentifierType $case
   *   The identifier type.
   *
   * @return class-string<\Drupal\Core\Database\Identifier\IdentifierBase>
   *   The class of the identifier type value object.
   */
  public static function valueObjectClass(self $case): string {
    return match ($case) {
      IdentifierType::Database => Database::class,
      IdentifierType::Schema => Schema::class,
      IdentifierType::Table => Table::class,
    };
  }

}
