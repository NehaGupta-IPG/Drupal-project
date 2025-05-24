<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a wrapper element.
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
