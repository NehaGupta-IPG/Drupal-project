<?php

declare(strict_types=1);

namespace Drupal\Component\HtmlAttribute;

use Drupal\Component\Utility\Html;

/**
 * A class defining a type of HtmlAttribute that can be added to as an array.
 *
 * To use with HtmlAttributeCollection, the array must be specified.
 * Correct:
 * @code
 *  $attributes = new HtmlAttributeCollection();
 *  $attributes['class'] = [];
 *  $attributes['class'][] = 'cat';
 * @endcode
 * Incorrect:
 * @code
 *  $attributes = new HtmlAttributeCollection();
 *  $attributes['class'][] = 'cat';
 * @endcode
 *
 * @see \Drupal\Component\HtmlAttribute\HtmlAttributeCollection
 *
 * @implements \ArrayAccess<string, scalar>
 * @implements \IteratorAggregate<string, scalar>
 */
class HtmlAttributeArray extends HtmlAttributeValueBase implements \ArrayAccess, \IteratorAggregate {

  /**
   * Ensures empty array as a result of array_filter will not print '$name=""'.
   *
   * @see \Drupal\Component\HtmlAttribute\HtmlAttributeArray::__toString()
   * @see \Drupal\Component\HtmlAttribute\HtmlAttributeValueBase::render()
   */
  const RENDER_EMPTY_ATTRIBUTE = FALSE;

  /**
   * Constructs an HtmlAttributeArray object.
   *
   * @param string $name
   *   The name of the value.
   * @param array<scalar> $arrayValue
   *   The value itself.
   */
  public function __construct(
    string $name,
    private array $arrayValue,
  ) {
    parent::__construct($name);
  }

  /**
   * Returns the raw value.
   *
   * @return array<scalar>
   *   The raw value.
   */
  protected function doGetValue(): array {
    return $this->arrayValue;
  }

  /**
   * Returns the value at the specified index.
   *
   * @param string|int $key
   *   The offset to retrieve.
   *
   * @return scalar
   *   The value at the specified index.
   */
  public function offsetGet(mixed $key): bool|float|int|string {
    return $this->arrayValue[$key];
  }

  /**
   * Sets the value at the specified index.
   *
   * @param string|int|null $key
   *   The offset to assign the value to.
   * @param scalar $value
   *   The value to set.
   */
  public function offsetSet(mixed $key, mixed $value): void {
    if (isset($key)) {
      $this->arrayValue[$key] = $value;
    }
    else {
      $this->arrayValue[] = $value;
    }
  }

  /**
   * Unsets the value at the specified index.
   *
   * @param string|int $key
   *   The offset to unset.
   */
  public function offsetUnset(mixed $key): void {
    unset($this->arrayValue[$key]);
  }

  /**
   * Returns whether the requested index exists.
   *
   * @param string|int $key
   *   An offset to check for.
   */
  public function offsetExists(mixed $key): bool {
    return isset($this->arrayValue[$key]);
  }

  /**
   * Implements the magic __toString() method.
   */
  public function __toString(): string {
    // Filter out any empty values before printing.
    $this->arrayValue = array_unique(array_filter($this->arrayValue));
    return Html::escape(implode(' ', $this->arrayValue));
  }

  /**
   * Retrieves an external iterator.
   *
   * @return \ArrayIterator<string|int, scalar>
   *   An external iterator.
   */
  public function getIterator(): \Traversable {
    return new \ArrayIterator($this->arrayValue);
  }

  /**
   * Exchange the array for another one.
   *
   * @param array<scalar> $input
   *   The array input to replace the internal value.
   *
   * @return array<scalar>
   *   The old array value.
   *
   * @see ArrayObject::exchangeArray
   */
  public function exchangeArray(array $input): array {
    $old = $this->arrayValue;
    $this->arrayValue = $input;
    return $old;
  }

}
