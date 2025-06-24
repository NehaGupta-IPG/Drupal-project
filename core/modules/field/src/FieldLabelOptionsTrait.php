<?php

namespace Drupal\field;

use Drupal\field\FieldLabelVisibility;

/**
 * Provides a trait for the valid field label options.
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
      FieldLabelVisibility::ABOVE->value => $this->t('Above'),
      FieldLabelVisibility::INLINE->value => $this->t('Inline'),
      FieldLabelVisibility::HIDDEN->value => '- ' . $this->t('Hidden') . ' -',
      FieldLabelVisibility::VISUALLY_HIDDEN->value => '- ' . $this->t('Visually Hidden') . ' -',
    ];
  }

}
