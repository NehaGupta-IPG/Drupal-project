<?php

declare(strict_types=1);

namespace Drupal\comment;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the comment field status options.
 */
enum CommentingStatus: int {

  case Hidden = 0;
  case Closed = 1;
  case Open = 2;

  /**
   * Gets the possible statuses for use in an options array.
   *
   * @return array<int, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   The translatable options array, keyed by the status value.
   */
  public static function asOptions(): array {
    return [
      self::Open->value => new TranslatableMarkup('Open'),
      self::Closed->value => new TranslatableMarkup('Closed'),
      self::Hidden->value => new TranslatableMarkup('Hidden'),
    ];
  }

}
