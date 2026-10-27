<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\FormElement;

/**
 * Provides a display-only form element with an optional title and description.
 *
 * Note: since this is a read-only field, setting the #required property will do
 * nothing except theme the form element to look as if it were actually required
 * (i.e. by placing a red star next to the #title).
 *
 * Properties:
 * - #markup: The data to be display.
 * - #title: Title of the form element.
 * - #description: Description of the element.
 *
 * Usage Example:
 * @code
 * $form['item_number'] = [
 *   '#type' => 'item',
 *   '#title' => $this->t('Priority'),
 *   '#markup' => $this->t('Important'),
 *   '#description' => $this->t('The priority of this task'),
 * ];
 * @endcode
 */
#[FormElement('item')]
class Item extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      // Forms that show author fields to both anonymous and authenticated users
      // need to dynamically switch between #type 'textfield' and #type 'item'
      // to automatically take over the authenticated user's information.
      // Therefore, we allow #type 'item' to receive input, which is internally
      // assigned by Form API based on the #default_value or #value properties.
      '#input' => TRUE,
      '#markup' => '',
      '#theme_wrappers' => ['form_element'],
    ];
  }

}
