<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * An helper class to convert SchemaDefinition objects to legacy array.
 *
 * @internal
 */
abstract class ConvertDefinition {

  /**
   * Converts an array of Table objects.
   *
   * @param array<int|string,\Drupal\Core\Database\SchemaDefinition\Table|<array<string,mixed>>> $schema
   *   An array of mixed Table objects or schema definition structure arrays.
   *
   * @return array<string<array<string,mixed>>>
   *   A schema definition structure array.
   *
   * @internal
   */
  final public static function schemaToArray(array $schema): array {
    $spec = [];
    foreach ($schema as $name => $table) {
      if ($table instanceof Table) {
        $spec[$table->name] = $table->toArray();
      }
      else {
        $spec[$name] = $table;
      }
    }
    return $spec;
  }

}
