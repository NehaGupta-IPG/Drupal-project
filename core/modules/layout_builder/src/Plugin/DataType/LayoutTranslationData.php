<?php

namespace Drupal\layout_builder\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\TypedData;

/**
 * Provides a data type wrapping \Drupal\layout_builder\Section.
 *
 * @internal
 *   Plugin classes are internal.
 */
#[DataType(
  id: 'layout_translation',
  label: new TranslatableMarkup('Layout translation'),
  description: new TranslatableMarkup('A layout translation'),
)]
class LayoutTranslationData extends TypedData {

  /**
   * The layout translation.
   *
   * @var array
   */
  protected $value;

}
