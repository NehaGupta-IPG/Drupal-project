<?php

namespace Drupal\system\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines GD2 resize operation.
 */
#[ImageToolkitOperation(
  id: "gd_resize",
  toolkit: "gd",
  operation: "resize",
  label: new TranslatableMarkup("Resize"),
  description: new TranslatableMarkup("Resizes an image to the given dimensions (ignoring aspect ratio).")
)]
class Resize extends GDImageToolkitOperationBase {

  // phpcs:disable
  /**
   * {@inheritdoc}
   */
  public bool $needsToolkitInjection {
    get => FALSE;
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'width' => [
        'description' => 'The new width of the resized image, in pixels',
      ],
      'height' => [
        'description' => 'The new height of the resized image, in pixels',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(ImageInterface|array $arguments, $extra = NULL) {
    if ($arguments instanceof ImageInterface) {
      $arguments = $extra;
      assert(is_array($arguments));
    }

    // Assure integers for all arguments.
    $arguments['width'] = (int) round($arguments['width']);
    $arguments['height'] = (int) round($arguments['height']);

    // Fail when width or height are 0 or negative.
    if ($arguments['width'] <= 0) {
      throw new \InvalidArgumentException("Invalid width ('{$arguments['width']}') specified for the image 'resize' operation");
    }
    if ($arguments['height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ('{$arguments['height']}') specified for the image 'resize' operation");
    }

    return $arguments;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(ImageInterface|array $arguments, $extra = NULL) {
    if ($arguments instanceof ImageInterface) {
      $image = $arguments;
      $arguments = $extra;
      assert(is_array($arguments));
    }
    else {
      // @todo fix.
    }

    // Create a new image of the required dimensions, and copy and resize
    // the original image on it with resampling.
    $original_gd_image = $image->getGdImageObject();
    $data = [
      'width' => $arguments['width'],
      'height' => $arguments['height'],
      'extension' => image_type_to_extension($image->getType(), FALSE),
      'transparent_color' => $image->toolkit()->getTransparentColor($image),
      'is_temp' => TRUE,
    ];
    if ($image->apply('create_new', $data)) {
      if (imagecopyresampled($image->getGdImageObject(), $original_gd_image, 0, 0, 0, 0, $arguments['width'], $arguments['height'], imagesx($original_gd_image), imagesy($original_gd_image))) {
        return TRUE;
      }
      // In case of failure, restore the original image.
      $image->setGdImageObject($original_gd_image);
    }
    return FALSE;
  }

}
