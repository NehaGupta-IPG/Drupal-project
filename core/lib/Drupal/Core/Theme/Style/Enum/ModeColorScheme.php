<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enumeration of the style option mode: color scheme.
 */
enum ModeColorScheme: string {

  case Any = 'any';
  case Light = 'light';
  case Dark = 'dark';

  /**
   * Returns the translated label of the mode.
   */
  public function label(): TranslatableMarkup {
    return match ($this) {
      self::Any => new TranslatableMarkup('Any'),
      self::Light => new TranslatableMarkup('Light'),
      self::Dark => new TranslatableMarkup('Dark'),
    };
  }

}
