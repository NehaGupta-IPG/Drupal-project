<?php

declare(strict_types=1);

namespace Drupal\comment;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enum for comment publishing status.
 */
enum CommentStatus: int {

  // Comment is awaiting approval.
  case NotPublished = 0;

  // Comment is published.
  case Published = 1;

  /**
   * Gets the comment status from a comment entity.
   */
  public static function fromComment(CommentInterface $comment): self {
    return $comment->isPublished() ? self::Published : self::NotPublished;
  }

  /**
   * Gets the possible statuses for use in an options array.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   The translatable options array, keyed by the status value.
   */
  public static function asOptions(): array {
    return [
      self::Published->value => new TranslatableMarkup('Published'),
      self::NotPublished->value => new TranslatableMarkup('Not published'),
    ];
  }

}
