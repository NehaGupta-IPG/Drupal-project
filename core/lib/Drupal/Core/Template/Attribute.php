<?php

namespace Drupal\Core\Template;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\HtmlAttribute\Attribute as ComponentAttribute;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Serialization\Attribute\JsonSchema;

/**
 * Collects, sanitizes, and renders HTML attributes.
 *
 * This class adds MarkupInterface, JSON serialization, and merging to the
 * parent class. See the parent class for usage.
 *
 * @see \Drupal\Component\Utility\HtmlAttribute\Attribute
 */
class Attribute extends ComponentAttribute implements MarkupInterface {

  /**
   * Implements the magic __toString() method.
   */
  #[JsonSchema(['type' => 'string', 'description' => 'Rendered HTML element attributes'])]
  public function __toString() {
    return parent::__toString();
  }

  /**
   * Returns a representation of the object for use in JSON serialization.
   *
   * @return string
   *   The safe string content.
   */
  public function jsonSerialize(): string {
    return (string) $this;
  }

  /**
   * Merges an Attribute object into the current storage.
   *
   * @param \Drupal\Core\Template\Attribute $collection
   *   The Attribute object to merge.
   *
   * @return $this
   */
  public function merge(Attribute $collection) {
    $merged_attributes = NestedArray::mergeDeep($this->toArray(), $collection->toArray());
    foreach ($merged_attributes as $name => $value) {
      $this->storage[$name] = $this->createAttributeValue($name, $value);
    }
    return $this;
  }

}
