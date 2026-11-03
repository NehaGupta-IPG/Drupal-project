<?php

declare(strict_types=1);

namespace Drupal\Core\Image;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enum for image resizing policies.
 */
enum ImageResizePolicy: string {

  case ResizeLargerImages = 'resize_larger_images';

  case RejectLargerImagesWithError = 'reject_larger_images_with_error';

  /**
   * Provide string-keyed options for form radios, checkboxes, or selects.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Array of the backed values as keys and corresponding human-readable
   *   labels as values.
   */
  public static function asOptions(): array {
    return [
      self::ResizeLargerImages->value => new TranslatableMarkup('Resize proportionally'),
      self::RejectLargerImagesWithError->value => new TranslatableMarkup('Reject'),
    ];
  }

}
