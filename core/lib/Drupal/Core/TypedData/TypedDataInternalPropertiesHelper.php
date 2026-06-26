<?php

namespace Drupal\Core\TypedData;

/**
 * Helper class for internal properties.
 */
class TypedDataInternalPropertiesHelper {

  /**
   * A WeakMap static cache of internal property names.
   */
  protected static ?\WeakMap $nonInternalPropertyNames;

  /**
   * Gets an array non-internal properties from a complex data object.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $data
   *   The complex data object.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface[]
   *   The non-internal properties, keyed by property name.
   */
  public static function getNonInternalProperties(ComplexDataInterface $data) {
    $properties = [];
    $definition = $data->getDataDefinition();
    if ($definition === NULL) {
      return [];
    }
    // When normalizing a large number of entities, calculating which properties
    // are not internal can be expensive. Also by first checking the property
    // definitions and only then getting the property values, this saves
    // initializing properties that won't be returned.
    if (!isset(static::$nonInternalPropertyNames[$definition])) {
      if (!isset(static::$nonInternalPropertyNames)) {
        static::$nonInternalPropertyNames = new \WeakMap();
      }
      $non_internal_properties = [];
      foreach ($definition->getPropertyDefinitions() as $name => $property_definition) {
        if (!$property_definition->isInternal()) {
          $non_internal_properties[] = $name;
        }
      }
      static::$nonInternalPropertyNames[$definition] = $non_internal_properties;
    }
    foreach (static::$nonInternalPropertyNames[$definition] as $name) {
      $properties[$name] = $data->get($name);
    }
    return $properties;
  }

}
