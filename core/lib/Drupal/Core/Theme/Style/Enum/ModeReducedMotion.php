<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enumeration of the style option mode: reduced motion.
 */
enum ModeReducedMotion: string {

  case Any = 'any';
  case Reduce = 'reduce';

  /**
   * Returns the translated label of the mode.
   */
  public function label(): TranslatableMarkup {
    return match ($this) {
      self::Any => new TranslatableMarkup('Any'),
      self::Reduce => new TranslatableMarkup('Reduce'),
    };
  }

}
