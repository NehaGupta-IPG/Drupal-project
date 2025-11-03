<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a render element for a form.
 *
 * Properties:
 * - #method: The form method: 'post' or 'get'.
 *
 * Usage example:
 * @code
 * $form = [
 *   '#method' => 'post',
 * ];
 * @endcode
 */
#[RenderElement('form')]
class Form extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#method' => 'post',
      '#theme_wrappers' => ['form'],
    ];
  }

}
