<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enumeration of the style types.
 */
enum Type: string {

  case Theme = 'theme';
  case Utility = 'utility';

  /**
   * Returns the translated label of the type.
   */
  public function label(): TranslatableMarkup {
    return match ($this) {
      self::Theme => new TranslatableMarkup('Theme'),
      self::Utility => new TranslatableMarkup('Utility'),
    };
  }

  /**
   * Returns the file name suffix used to discover styles of the type.
   */
  public function discoveryName(): string {
    return match ($this) {
      self::Theme => 'themes',
      self::Utility => 'utilities',
    };
  }

}
