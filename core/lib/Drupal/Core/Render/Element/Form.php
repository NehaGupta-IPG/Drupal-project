<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a render element for a form.
 *
 * Properties:
 * - #ajax: (optional) Default AJAX settings for submit buttons without an
 *   individual #ajax property. The form's ID is the default wrapper. Normal
 *   validation and submission run before the AJAX callback.
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
