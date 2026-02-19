<?php

namespace Drupal\Core\Template;

use Drupal\Component\HtmlAttribute\HtmlAttributeCollection;
use Drupal\Component\Utility\NestedArray;

/**
 * Helper class for mixed array and HtmlAttributeCollection operations.
 *
 * This class contains static methods only and is not meant to be instantiated.
 */
class AttributeHelper {

  /**
   * This class should not be instantiated.
   */
  private function __construct() {
  }

  /**
   * Checks if the given attribute collection has an attribute.
   *
   * @param string $name
   *   The name of the attribute to check for.
   * @param \Drupal\Component\HtmlAttribute\HtmlAttributeCollection|array $collection
   *   An HtmlAttributeCollection object or an array of attributes.
   *
   * @return bool
   *   TRUE if the attribute exists, FALSE otherwise.
   *
   * @throws \InvalidArgumentException
   *   When the input $collection is neither an HtmlAttributeCollection object
   *   nor an array.
   */
  public static function attributeExists($name, $collection) {
    if ($collection instanceof HtmlAttributeCollection) {
      return $collection->hasAttribute($name);
    }
    elseif (is_array($collection)) {
      return array_key_exists($name, $collection);
    }
    throw new \InvalidArgumentException('Invalid collection argument');
  }

  /**
   * Merges two attribute collections.
   *
   * @param \Drupal\Component\HtmlAttribute\HtmlAttributeCollection|array $a
   *   First Attribute object or array to merge. The returned value type will
   *   be the same as the type of this argument.
   * @param \Drupal\Component\HtmlAttribute\HtmlAttributeCollection|array $b
   *   Second Attribute object or array to merge.
   *
   * @return \Drupal\Component\HtmlAttribute\HtmlAttributeCollection|array
   *   The merged attributes, as an Attribute object or an array.
   *
   * @throws \InvalidArgumentException
   *   If at least one collection argument is neither an
   *   HtmlAttributeCollection object nor an array.
   */
  public static function mergeCollections($a, $b) {
    if (!($a instanceof HtmlAttributeCollection || is_array($a)) || !($b instanceof HtmlAttributeCollection || is_array($b))) {
      throw new \InvalidArgumentException('Invalid collection argument');
    }
    // If both collections are arrays, just merge them.
    if (is_array($a) && is_array($b)) {
      return NestedArray::mergeDeep($a, $b);
    }
    // If at least one collections is an HtmlAttributeCollection object, merge
    // through HtmlAttributeCollection::merge.
    $merge_a = $a instanceof HtmlAttributeCollection ? $a : new HtmlAttributeCollection($a);
    $merge_b = $b instanceof HtmlAttributeCollection ? $b : new HtmlAttributeCollection($b);
    $merge_a->merge($merge_b);
    return $a instanceof HtmlAttributeCollection ? $merge_a : $merge_a->toArray();
  }

}
