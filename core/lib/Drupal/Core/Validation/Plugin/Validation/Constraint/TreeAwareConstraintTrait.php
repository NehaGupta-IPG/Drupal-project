<?php

declare(strict_types=1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Helper methods for tree-aware validation constraints.
 *
 * @todo Figure out whether to deal with TraversableTypedDataInterface, ComplexDataInterface, Mapping, or something else 😬
 * @todo Similarly, figure out whether to return TypedDataInterface or \Drupal\Core\Config\Schema\Element 😬
 */
trait TreeAwareConstraintTrait {

  /**
   * Finds the parent property.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The parent property.
   */
  private function getParentProperty(): TypedDataInterface {
    $parent_property_path = array_slice(explode('.', $this->context->getPropertyPath()), 0, -1);
    return self::findPropertyForPath($this->context->getRoot(), $parent_property_path);
  }

  /**
   * Finds the specified property path in the given tree.
   *
   * @todo consider adopting Symfony's PropertyAccess component.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $tree
   *   A config schema (sub)tree.
   * @param string[] $property_path
   *   A property path, in array form.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The property found at the specified property path.
   *
   * @throws \OutOfRangeException
   *   When requesting a non-existent property path.
   */
  private static function findPropertyForPath(ComplexDataInterface $tree, array $property_path): TypedDataInterface {
    // Edge case: root is requested.
    if (empty($property_path)) {
      return $tree;
    }

    $elements = $tree->getElements();
    $name = array_shift($property_path);

    if (!isset($elements[$name])) {
      throw new \OutOfRangeException();
    }
    if (empty($property_path)) {
      return $elements[$name];
    }
    return self::findPropertyForPath($elements[$name], $property_path);
  }

}
