<?php

namespace Drupal\field;

/**
 * Provides a trait for the valid field label options.
 *
 * @category Field
 * @package Drupal\field
 * @license https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0+
 * @link https://www.drupal.org
 */
trait FieldLabelOptionsTrait {

  /**
   * Returns an array of visibility options for field labels.
   *
   * @return array
   *   An array of visibility options.
   */
  protected function getFieldLabelOptions(): array {
    return [
      FieldLabelVisibility::Above->value => $this->t('Above'),
      FieldLabelVisibility::Inline->value => $this->t('Inline'),
      FieldLabelVisibility::Hidden->value => '- ' . $this->t('Hidden') . ' -',
      FieldLabelVisibility::VisuallyHidden->value => '- ' . $this->t('Visually Hidden') . ' -',
    ];
  }

}
