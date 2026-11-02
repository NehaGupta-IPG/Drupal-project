<?php

namespace Drupal\views\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Defines a field handler which renders multiple items per row.
 */
interface MultiItemsFieldHandlerInterface extends FieldHandlerInterface {

  /**
   * Renders a single item of a row.
   *
   * @param int $count
   *   The index of the item inside the row.
   * @param mixed $item
   *   The item for the field to render.
   *
   * @return string
   *   The rendered output.
   *
   * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0.
   *   Use renderItem() instead.
   *
   * @see https://www.drupal.org/node/3467146
   */
  public function render_item($count, $item);

  /**
   * Renders a single item of a row.
   *
   * @param int|string $count
   *   The index of the item inside the row.
   * @param mixed $item
   *   The item for the field to render.
   *
   * @return object|string
   *   The rendered output.
   */
  public function renderItem(int|string $count, array $item): object|string;

  /**
   * Gets an array of items for the field.
   *
   * @param \Drupal\views\ResultRow $values
   *   The result row object containing the values.
   *
   * @return array
   *   An array of items for the field.
   */
  public function getItems(ResultRow $values);

  /**
   * Render all items in this field together.
   *
   * @param array $items
   *   The items provided by getItems for a single row.
   *
   * @return string|null
   *   The rendered items.
   */
  public function renderItems($items);

}
