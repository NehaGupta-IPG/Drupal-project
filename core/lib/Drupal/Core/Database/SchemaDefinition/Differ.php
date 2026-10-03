<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Extracts a path-based diff between two SchemaDefinitionInterface objects.
 */
class Differ {

  /**
   * Computes the diff.
   */
  public static function diff(mixed $old, mixed $new, string $path = ''): array {
    $diff = [];

    // SchemaDefinitionInterface objects: compare their properties.
    if ($old instanceof SchemaDefinitionInterface && $new instanceof SchemaDefinitionInterface) {
      // When comparing simple arrays of SchemaDefinitionInterface objects,
      // normalize them to associative arrays when the sequence of values in the
      // simple array is not relevant.
      $arrayPropertyNormalizationKeys = $old->arrayPropertyNormalizationKeys();

      $old = get_object_vars($old);
      $new = get_object_vars($new);
    }
    else {
      $arrayPropertyNormalizationKeys = [];
    }

    // Compare array keys recursively.
    if (is_array($old) && is_array($new)) {
      $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

      foreach ($allKeys as $key) {
        $pathFragmentA = $old[$key] ?? $new[$key];
        $pathFragmentB = $pathFragmentA instanceof SchemaDefinitionInterface && property_exists($pathFragmentA, 'name') && is_int($key) ? $pathFragmentA->name : NULL;
        $pathFragmentC = $pathFragmentB !== NULL ? '[' . (string) $key . ']' . $pathFragmentB : (string) $key;

        $currentPath = $path === '' ? $pathFragmentC : $path . '.' . $pathFragmentC;

        $existsOld = array_key_exists($key, $old);
        $existsNew = array_key_exists($key, $new);

        if (!$existsOld) {
          $diff[$currentPath] = ['type' => 'added', 'new' => $new[$key]];
          continue;
        }

        if (!$existsNew) {
          $diff[$currentPath] = ['type' => 'removed', 'old' => $old[$key]];
          continue;
        }

        if (isset($arrayPropertyNormalizationKeys[$key])) {
          $tmp = [];
          foreach ($old[$key] ?? [] as $value) {
            $tmp[$value->{$arrayPropertyNormalizationKeys[$key]}] = $value;
          }
          $old[$key] = $tmp;
          $tmp = [];
          foreach ($new[$key] ?? [] as $value) {
            $tmp[$value->{$arrayPropertyNormalizationKeys[$key]}] = $value;
          }
          $new[$key] = $tmp;
        }

        $diff = array_merge($diff, static::diff($old[$key], $new[$key], $currentPath));
      }

      return $diff;
    }

    // Different types, or one value is no longer an object/array.
    if (get_debug_type($old) !== get_debug_type($new) || $old !== $new) {
      $diff[$path] = ['type' => 'changed', 'old' => $old, 'new' => $new];
    }

    return $diff;
  }

}
