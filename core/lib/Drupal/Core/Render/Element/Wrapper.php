<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides an empty placeholder element (internal).
 *
 * @internal
 */
#[RenderElement('wrapper')]
class Wrapper extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [];
  }

}
