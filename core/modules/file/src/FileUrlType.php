<?php

namespace Drupal\file;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\OptionsEnumTrait;

/**
 * Enum to specify whether file URL is absolute or relative.
 */
enum FileUrlType: string {

  use OptionsEnumTrait;

  // Display URL as absolute.
  case Absolute = 'absolute';

  // Display URL as relative.
  case Relative = 'relative';

  /**
   * {@inheritdoc}
   */
  public function label(): string|\Stringable {
    return match ($this) {
      self::Absolute => new TranslatableMarkup('Absolute'),
      self::Relative => new TranslatableMarkup('Closed'),
    };
  }

}
