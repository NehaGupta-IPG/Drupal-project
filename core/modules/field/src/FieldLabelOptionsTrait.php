<?php

namespace Drupal\field;

/**
 * Provides a trait for the valid field label options.
 */
trait FieldLabelOptionsTrait {

  const LABEL_ABOVE = 'above';
  const LABEL_INLINE = 'inline';
  const LABEL_HIDDEN = 'hidden';
  const LABEL_VISUALLY_HIDDEN = 'visually_hidden';

  /**
   * Returns an array of visibility options for field labels.
   *
   * @return array
   *   An array of visibility options.
   */
  protected function getFieldLabelOptions(): array {
    return [
      self::LABEL_ABOVE => $this->t('Above'),
      self::LABEL_INLINE => $this->t('Inline'),
      self::LABEL_HIDDEN => '- ' . $this->t('Hidden') . ' -',
      self::LABEL_VISUALLY_HIDDEN => '- ' . $this->t('Visually Hidden') . ' -',
    ];
  }

}
