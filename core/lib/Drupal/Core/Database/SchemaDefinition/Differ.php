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
  public static function diff(SchemaDefinitionInterface $old, SchemaDefinitionInterface $new): array {
    if (get_class($old) !== get_class($new)) {
      throw new \InvalidArgumentException('Schema diff can only be computed between same type of SchemaDefinition objects');
    }
    return static::computeDiff($old, $new);
  }

  /**
   * Computes the diff.
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
            $pathFragmentValue = "[" . $pathFragmentElement->{$pathFragmentNormalizationProperty} . "]";
          }
          else {
            $pathFragmentValue = '';
          }
        }
        else {
          $pathFragmentValue = ($path === NULL ? '' : '.') . $key;
        }

        $currentPath = $path === NULL ? $pathFragmentValue : $path . $pathFragmentValue;

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

        $diff = array_merge($diff, static::computeDiff($old[$key], $new[$key], $currentPath));
      }

      return $diff;
    }

    // Different types, or one value is no longer an object/array.
    if (get_debug_type($old) !== get_debug_type($new) || $old != $new) {
      $diff[$path] = ['type' => 'changed', 'old' => $old, 'new' => $new];
    }

    return $diff;
  }

}
