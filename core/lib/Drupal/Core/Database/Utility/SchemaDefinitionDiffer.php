<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Utility;

use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionInterface;

/**
 * Extracts a path-based diff between two SchemaDefinitionInterface objects.
 *
 * @phpstan-type DiffEntry array{
 *   'type': SchemaDefinitionDiffEntryType,
 *   'old'?: scalar|array|SchemaDefinitionInterface,
 *   'new'?: scalar|array|SchemaDefinitionInterface,
 * }
 */
class SchemaDefinitionDiffer {

  /**
   * Returns the diff between two SchemaDefinitionInterface objects.
   *
   * @param SchemaDefinitionInterface $old
   *   A SchemaDefinitionInterface object to compare.
   * @param SchemaDefinitionInterface $new
   *   A SchemaDefinitionInterface object to compare.
   *
   * @return array<string,DiffEntry>
   *   A list of diff entries.
   */
  public static function diff(SchemaDefinitionInterface $old, SchemaDefinitionInterface $new): array {
    if (get_class($old) !== get_class($new)) {
      throw new \InvalidArgumentException('Schema diff can only be computed between same type of SchemaDefinition objects');
    }
    return static::computeDiff($old, $new);
  }

  /**
   * Recursively compute the diff.
   *
   * @param scalar|array|SchemaDefinitionInterface $old
   *   A property to compare.
   * @param scalar|array|SchemaDefinitionInterface $new
   *   A property to compare.
   * @param ?string $path
   *   The current diff path.
   *
   * @return array<string,DiffEntry>
   *   A list of diff entries.
   */
  protected static function computeDiff(mixed $old, mixed $new, ?string $path = NULL): array {
    $diff = [];

    // SchemaDefinitionInterface objects: compare their properties.
    if ($old instanceof SchemaDefinitionInterface && $new instanceof SchemaDefinitionInterface) {
      $old = get_object_vars($old);
      $new = get_object_vars($new);
    }

    // Compare array keys recursively.
    if (is_array($old) && is_array($new)) {
      $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

      foreach ($allKeys as $key) {
        $pathFragmentElement = $old[$key] ?? $new[$key];
        if ($pathFragmentElement instanceof SchemaDefinitionInterface) {
          $pathFragmentClass = get_class($pathFragmentElement);
          $pathFragmentNormalizationProperty = $pathFragmentClass::normalizationProperty();
          if ($pathFragmentNormalizationProperty) {
            /** @var string $normalizedKey */
            $normalizedKey = $pathFragmentElement->{$pathFragmentNormalizationProperty};
            $pathFragmentValue = "[" . $normalizedKey . "]";
          }
          else {
            $pathFragmentValue = ($path === NULL ? '' : '.') . $key;
          }
        }
        else {
          $pathFragmentValue = ($path === NULL ? '' : '.') . $key;
        }

        $currentPath = $path === NULL ? $pathFragmentValue : $path . $pathFragmentValue;

        $existsOld = array_key_exists($key, $old);
        $existsNew = array_key_exists($key, $new);

        if (!$existsOld) {
          $diff[$currentPath] = [
            'type' => SchemaDefinitionDiffEntryType::Added,
            'new' => $new[$key],
          ];
          continue;
        }

        if (!$existsNew) {
          $diff[$currentPath] = [
            'type' => SchemaDefinitionDiffEntryType::Removed,
            'old' => $old[$key],
          ];
          continue;
        }

        $oldFirstArrayKey = is_array($old[$key]) ? array_first($old[$key]) : NULL;
        $newFirstArrayKey = is_array($new[$key]) ? array_first($new[$key]) : NULL;
        $isSchemaDefinitionArray = ($oldFirstArrayKey ?? $newFirstArrayKey) instanceof SchemaDefinitionInterface;
        if ($isSchemaDefinitionArray) {
          $schemaDefinitionClass = get_class($oldFirstArrayKey ?? $newFirstArrayKey);
          $schemaDefinitionNormalizationProperty = $schemaDefinitionClass::normalizationProperty();
          $tmp = [];
          foreach ($old[$key] ?? [] as $value) {
            /** @var string $normalizedKey */
            $normalizedKey = $value->{$schemaDefinitionNormalizationProperty};
            $tmp[$normalizedKey] = $value;
          }
          $old[$key] = $tmp;
          $tmp = [];
          assert(is_array($new[$key]));
          foreach ($new[$key] as $value) {
            /** @var string $normalizedKey */
            $normalizedKey = $value->{$schemaDefinitionNormalizationProperty};
            $tmp[$normalizedKey] = $value;
          }
          $new[$key] = $tmp;
        }

        /** @var scalar|array|SchemaDefinitionInterface $oldKey */
        $oldKey = $old[$key];
        /** @var scalar|array|SchemaDefinitionInterface $newKey */
        $newKey = $new[$key];

        $diff = array_merge($diff, static::computeDiff($oldKey, $newKey, $currentPath));
      }

      /** @var array<string,DiffEntry> $diff */
      return $diff;
    }

    // Different types, or one value is no longer an object/array.
    if ($old != $new) {
      assert(is_string($path), 'Invalid diff path.');
      $diff[$path] = [
        'type' => SchemaDefinitionDiffEntryType::Changed,
        'old' => $old,
        'new' => $new,
      ];
    }

    return $diff;
  }

}
