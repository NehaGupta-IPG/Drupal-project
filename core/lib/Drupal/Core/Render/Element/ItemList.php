<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a render element for displaying an item list.
 */
#[RenderElement('item_list')]
class ItemList extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'item_list',
      '#items' => [],
      '#title' => NULL,
      '#list_type' => 'ul',
      '#empty' => NULL,
      '#attributes' => [],
      '#wrapper_attributes' => [],
      '#context' => [],
    ];
  }

}
