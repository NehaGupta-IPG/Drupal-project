<?php

namespace Drupal\Component\Utility;

/**
 * A class that defines a type of attribute that can be added to as an array.
 *
 * To use with HtmlAttributes, the array must be specified.
 * Correct:
 * @code
 *  $attributes = new HtmlAttributes();
 *  $attributes['class'] = [];
 *  $attributes['class'][] = 'cat';
 * @endcode
 * Incorrect:
 * @code
 *  $attributes = new HtmlAttributes();
 *  $attributes['class'][] = 'cat';
 * @endcode
 *
 * @implements \ArrayAccess<int|string, mixed>
 * @implements \IteratorAggregate<int|string, mixed>
 *
 * @see \Drupal\Component\Utility\HtmlAttributes
 */
class HtmlAttributeArray extends HtmlAttributeValueBase implements \ArrayAccess, \IteratorAggregate {

  /**
   * Ensures empty array as a result of array_filter will not print '$name=""'.
   *
   * @see \Drupal\Component\Utility\HtmlAttributeArray::__toString()
   * @see \Drupal\Component\Utility\HtmlAttributeValueBase::render()
   */
  const RENDER_EMPTY_ATTRIBUTE = FALSE;

  /**
   * {@inheritdoc}
   */
  public function offsetGet($offset): mixed {
    return $this->value[$offset];
  }

  /**
   * {@inheritdoc}
   */
  public function offsetSet($offset, $value): void {
    if (isset($offset)) {
      $this->value[$offset] = $value;
    }
    else {
      $this->value[] = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function offsetUnset($offset): void {
    unset($this->value[$offset]);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetExists($offset): bool {
    return isset($this->value[$offset]);
  }

  /**
   * Implements the magic __toString() method.
   */
  public function __toString() {
    // Filter out any empty values before printing.
    $this->value = array_unique(array_filter($this->value));
    return Html::escape(implode(' ', $this->value));
  }

  /**
   * Retrieves the iterator for the object.
   *
   * @return \ArrayIterator<int|string, mixed>
   *   The iterator.
   */
  public function getIterator(): \ArrayIterator {
    return new \ArrayIterator($this->value);
  }

  /**
   * Exchange the array for another one.
   *
   * @param array $input
   *   The array input to replace the internal value.
   *
   * @return array
   *   The old array value.
   *
   * @see ArrayObject::exchangeArray
   */
  public function exchangeArray($input) {
    $old = $this->value;
    $this->value = $input;
    return $old;
  }

}
