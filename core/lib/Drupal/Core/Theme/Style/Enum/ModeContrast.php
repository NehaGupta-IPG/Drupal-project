<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enumeration of the style option mode: contrast.
 */
enum ModeContrast: string {

  case Any = 'any';
  case More = 'more';
  case Less = 'less';

  /**
   * Returns the translated label of the mode.
   */
  public function label(): TranslatableMarkup {
    return match ($this) {
      self::Any => new TranslatableMarkup('Any'),
      self::More => new TranslatableMarkup('More'),
      self::Less => new TranslatableMarkup('Less'),
    };
  }

}
