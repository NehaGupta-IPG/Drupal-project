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
   * @param bool $areArrayElements
   *   TRUE when a $old and $new are arrays of SchemaDefinition elements.
   *
   * @return array<string,DiffEntry>
   *   A list of diff entries.
   */
  protected static function computeDiff(mixed $old, mixed $new, ?string $path = NULL, bool $areArrayElements = FALSE): array {
    $diff = [];

    // For SchemaDefinitionInterface objects, we compare their properties.
    if ($old instanceof SchemaDefinitionInterface && $new instanceof SchemaDefinitionInterface) {
      $old = get_object_vars($old);
      $new = get_object_vars($new);
    }

    // Compare array keys recursively.
    if (is_array($old) && is_array($new)) {
      $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

      foreach ($allKeys as $key) {
        $pathFragmentValue = $areArrayElements ? "[{$key}]" : (($path === NULL ? '' : '.') . $key);
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

        $oldFirstArrayKey = is_array($old[$key] ?? NULL) ? array_first($old[$key]) : NULL;
        $newFirstArrayKey = is_array($new[$key] ?? NULL) ? array_first($new[$key]) : NULL;
        $isSchemaDefinitionArray = ($oldFirstArrayKey ?? $newFirstArrayKey) instanceof SchemaDefinitionInterface;
        if ($isSchemaDefinitionArray) {
          $schemaDefinitionClass = get_class($oldFirstArrayKey ?? $newFirstArrayKey);
          $schemaDefinitionNormalizationProperty = $schemaDefinitionClass::normalizationProperty();
          if (isset($schemaDefinitionNormalizationProperty)) {
            $oldKeyAsArray = $old[$key] ?? [];
            $newKeyAsArray = $new[$key] ?? [];
            assert(is_iterable($newKeyAsArray));
            $tmp = [];
            foreach ($oldKeyAsArray as $value) {
              /** @var string $normalizedKey */
              $normalizedKey = $value->{$schemaDefinitionNormalizationProperty};
              $tmp[$normalizedKey] = $value;
            }
            $old[$key] = $tmp;
            $tmp = [];
            foreach ($newKeyAsArray as $value) {
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
        }
        else {
          /** @var scalar|array|SchemaDefinitionInterface $oldKey */
          $oldKey = $old[$key];
          /** @var scalar|array|SchemaDefinitionInterface $newKey */
          $newKey = $new[$key];
        }


        $diff = array_merge($diff, static::computeDiff($oldKey, $newKey, $currentPath, is_array($oldKey) && is_array($newKey)));
      }

      /** @var array<string,DiffEntry> $diff */
      return $diff;
    }

    // Actual comparison.
    if ($old != $new) {
      assert(is_string($path), 'Invalid diff path.');
      if ($old !== NULL && $new !== NULL) {
        $diff[$path] = [
          'type' => SchemaDefinitionDiffEntryType::Changed,
          'old' => $old,
          'new' => $new,
        ];
      }
      elseif ($old === NULL) {
        $diff[$path] = [
          'type' => SchemaDefinitionDiffEntryType::Set,
          'old' => $old,
          'new' => $new,
        ];
      }
      else {
        $diff[$path] = [
          'type' => SchemaDefinitionDiffEntryType::Unset,
          'old' => $old,
          'new' => $new,
        ];
      }
    }

    return $diff;
  }

}
