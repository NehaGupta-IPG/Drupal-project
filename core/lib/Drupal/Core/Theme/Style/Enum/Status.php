<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enumeration of the style and style option statuses.
 */
enum Status: string {

  case Experimental = 'experimental';
  case Stable = 'stable';
  case Deprecated = 'deprecated';
  case Obsolete = 'obsolete';

  /**
   * Returns the translated label of the status.
   */
  public function label(): TranslatableMarkup {
    return match ($this) {
      self::Experimental => new TranslatableMarkup('Experimental'),
      self::Stable => new TranslatableMarkup('Stable'),
      self::Deprecated => new TranslatableMarkup('Deprecated'),
      self::Obsolete => new TranslatableMarkup('Obsolete'),
    };
  }

  /**
   * Check if the status should trigger a warning.
   */
  public function triggerWarning(): bool {
    return match ($this) {
      self::Experimental => FALSE,
      self::Stable => FALSE,
      self::Deprecated => TRUE,
      self::Obsolete => TRUE,
    };
  }

}
