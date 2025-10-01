<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enumeration of the style types.
 */
enum Type: string {

  case Mode = 'mode';
  case Utility = 'utility';

  /**
   * Returns the translated label of the type.
   */
  public function label(): TranslatableMarkup {
    return match ($this) {
      self::Mode => new TranslatableMarkup('Mode'),
      self::Utility => new TranslatableMarkup('Utility'),
    };
  }

}
