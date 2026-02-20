<?php

namespace Drupal\system\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines GD2 convert operation.
 */
#[ImageToolkitOperation(
  id: "gd_convert",
  toolkit: "gd",
  operation: "convert",
  label: new TranslatableMarkup("Convert"),
  description: new TranslatableMarkup("Instructs the toolkit to save the image with a specified extension.")
)]
class Convert extends GDImageToolkitOperationBase {

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
      'extension' => [
        'description' => 'The new extension of the converted image',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(ImageInterface|array $arguments, $extra = NULL) {
    if ($arguments instanceof ImageInterface) {
      // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
      $image = $arguments;
      $arguments = $extra;
      assert(is_array($arguments));
    }
    else {
      // @todo fix.
    }

    if (!in_array($arguments['extension'], $image->getToolkit()->getSupportedExtensions())) {
      throw new \InvalidArgumentException("Invalid extension ({$arguments['extension']}) specified for the image 'convert' operation");
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

    // Create a new image of the required dimensions and format, and copy
    // the original image on it with resampling. Restore the original image upon
    // failure.
    $width = $image->getWidth();
    $height = $image->getHeight();
    $original_gd_image = $image->getGdImageObject();
    $original_type = $image->getType();
    $data = [
      'width' => $width,
      'height' => $height,
      'extension' => $arguments['extension'],
      'transparent_color' => $image->getToolkit()->getTransparentColor($image),
      'is_temp' => TRUE,
    ];
    if ($image->apply('create_new', $data)) {
      if (imagecopyresampled($image->getGdImageObject(), $original_gd_image, 0, 0, 0, 0, $width, $height, $width, $height)) {
        return TRUE;
      }
      // In case of error, reset image and type to as it was.
      $image->setGdImageObject($original_gd_image);
      $image->setType($original_type);
    }
    return FALSE;
  }

}
