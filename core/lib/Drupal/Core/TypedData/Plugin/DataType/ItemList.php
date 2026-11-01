<?php

namespace Drupal\Core\TypedData\Plugin\DataType;

use Drupal\Component\Utility\FilterArray;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TypedData;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * A generic list class.
 *
 * This class can serve as list for any type of items and is used by default.
 * Data types may specify the default list class in their definition, see
 * Drupal\Core\TypedData\Annotation\DataType.
 * Note: The class cannot be called "List" as list is a reserved PHP keyword.
 *
 * @template T of \Drupal\Core\TypedData\TypedDataInterface
 * @implements \IteratorAggregate<int, T>
 * @implements \Drupal\Core\TypedData\ListInterface<T>
 *
 * @ingroup typed_data
 */
#[DataType(
  id: "list",
  label: new TranslatableMarkup("List of items"),
  definition_class: ListDataDefinition::class,
)]
class ItemList extends TypedData implements \IteratorAggregate, ListInterface {

  /**
   * Numerically indexed array of items.
   *
   * @var array<int,\Drupal\Core\TypedData\TypedDataInterface>
   */
  protected $list = [];

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->list;
  }

  /**
   * Overrides \Drupal\Core\TypedData\TypedData::setValue().
   *
   * @param array|null $values
   *   An array of values of the field items, or NULL to unset the field.
   * @param bool $notify
   *   (optional) Whether to notify the parent object of the change. Defaults to
   *   TRUE.
   */
  public function setValue($values, $notify = TRUE) {
    if (!isset($values) || $values === []) {
      $this->list = [];
    }
    else {
      // Only arrays with numeric keys are supported.
      if (!is_array($values)) {
        throw new \InvalidArgumentException('Cannot set a list with a non-array value.');
      }
      foreach (array_values($values) as $key => $value) {
        $item = $this->createItem($key, $value);
        $this->list[$key] = $item->getValue();
      }
    }
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getString() {
    $strings = [];
    foreach ($this->list as $index => $item) {
      $typed_data = $this->createItem($index, $item);
      $strings[] = $typed_data->getString();
    }
    // Remove any empty strings resulting from empty items.
    return implode(', ', FilterArray::removeEmptyStrings($strings));
  }

  /**
   * {@inheritdoc}
   */
  public function get($index) {
    if (!is_numeric($index)) {
      throw new \InvalidArgumentException('Unable to get a value with a non-numeric delta in a list.');
    }
    if (isset($this->list[$index])) {
      $return = $this->createItem($index, $this->list[$index]);
      return $return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function set($index, $value) {
    if (!is_numeric($index)) {
      throw new \InvalidArgumentException('Unable to set a value with a non-numeric delta in a list.');
    }
    // Ensure indexes stay sequential. We allow assigning an item at an existing
    // index, or at the next index available.
    if ($index < 0 || $index > count($this->list)) {
      throw new \InvalidArgumentException('Unable to set a value to a non-subsequent delta in a list.');
    }
    // Support setting values via typed data objects.
    if ($value instanceof TypedDataInterface) {
      $value = $value->getValue();
    }
    else {
      $item = $this->createItem($index, $value);
      $value = $item->getValue();
    }
    $this->list[$index] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem($index) {
    if (isset($this->list) && array_key_exists($index, $this->list)) {
      // Remove the item, and reassign deltas.
      unset($this->list[$index]);
      $this->rekey($index);
    }
    else {
      throw new \InvalidArgumentException('Unable to remove item at non-existing index.');
    }
    return $this;
  }

  /**
   * Renumbers the items in the list.
   *
   * @param int $from_index
   *   Optionally, the index at which to start the renumbering, if it is known
   *   that items before that can safely be skipped (for example, when removing
   *   an item at a given index).
   */
  protected function rekey($from_index = 0) {
    // Re-key the list to maintain consecutive indexes.
    $this->list = array_values($this->list);
  }

  /**
   * {@inheritdoc}
   */
  public function first() {
    return $this->get(0);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetExists($offset): bool {
    // We do not want to throw exceptions here, so we do not use get().
    return isset($this->list[$offset]);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetUnset($offset): void {
    $this->removeItem($offset);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetGet($offset): mixed {
    return $this->get($offset);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetSet($offset, $value): void {
    if (!isset($offset)) {
      // The [] operator has been used.
      $this->appendItem($value);
    }
    else {
      $this->set($offset, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function appendItem($value = NULL) {
    $offset = count($this->list);
    $this->list[$offset] = $value;
    $item = $this->createItem($offset, $value);
    return $item;
  }

  /**
   * Helper for creating a list item object.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The new property instance.
   */
  protected function createItem($offset = 0, $value = NULL) {
    return $this->getTypedDataManager()->getPropertyInstance($this, $offset, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function getItemDefinition() {
    return $this->definition->getItemDefinition();
  }

  /**
   * Retrieves the iterator for the object.
   *
   * @return \ArrayIterator<int,\Drupal\Core\TypedData\TypedDataInterface>
   *   The iterator.
   */
  public function getIterator(): \ArrayIterator {
    $list = [];
    foreach ($this->list as $index => $value) {
      $list[$index] = $this->createItem($index, $value);
    }
    return new \ArrayIterator($list);
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return count($this->list);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    foreach ($this->list as $index => $value) {
      $item = $this->createItem($index, $value);
      if ($item instanceof ComplexDataInterface || $item instanceof ListInterface) {
        if (!$item->isEmpty()) {
          return FALSE;
        }
      }
      // Other items are treated as empty if they have no value only.
      elseif ($item->getValue() !== NULL) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function filter($callback) {
    if (isset($this->list)) {
      $removed = FALSE;
      // Apply the filter, detecting if some items were actually removed.
      $this->list = array_filter($this->list, function ($value, $key) use ($callback, &$removed) {
        $item = $this->createItem($key, $value);
        if (call_user_func($callback, $item)) {
          return TRUE;
        }
        else {
          $removed = TRUE;
        }
      }, ARRAY_FILTER_USE_BOTH);
      if ($removed) {
        $this->rekey();
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($delta) {
    // Notify the parent of changes.
    if (isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * Magic method: Implements a deep clone.
   */
  public function __clone() {
    foreach ($this->list as $delta => $item) {
      $this->list[$delta] = clone $item;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function last(): ?TypedDataInterface {
    return $this->get($this->count() - 1);
  }

}
