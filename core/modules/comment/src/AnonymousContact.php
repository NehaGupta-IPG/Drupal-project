<?php

declare(strict_types=1);

namespace Drupal\comment;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the anonymous contact options for comments.
 */
enum AnonymousContact: int {

  // Anonymous posters cannot enter their contact information.
  case Forbidden = 0;

  // Anonymous posters may leave their contact information.
  case Allowed = 1;

  // Anonymous posters are required to leave their contact information.
  case Required = 2;

  /**
   * Gets the possible values for use in an options array.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   The translatable options array, keyed by the status value.
   */
  public static function asOptions(): array {
    return [
      self::Forbidden->value => new TranslatableMarkup('Anonymous posters may not enter their contact information'),
      self::Allowed->value => new TranslatableMarkup('Anonymous posters may leave their contact information'),
      self::Required->value => new TranslatableMarkup('Anonymous posters must leave their contact information'),
    ];
  }

}
