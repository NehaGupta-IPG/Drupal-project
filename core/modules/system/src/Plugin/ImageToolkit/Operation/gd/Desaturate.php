<?php

namespace Drupal\system\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines GD2 Desaturate operation.
 */
#[ImageToolkitOperation(
  id: "gd_desaturate",
  toolkit: "gd",
  operation: "desaturate",
  label: new TranslatableMarkup("Desaturate"),
  description: new TranslatableMarkup("Converts an image to grayscale.")
)]
class Desaturate extends GDImageToolkitOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    // This operation does not use any parameters.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(ImageInterface|array $arguments, $extra = NULL) {
    if ($arguments instanceof ImageInterface) {
      // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
      $image = $arguments;
      $arguments = $extra;
      assert(is_array($arguments));
    }
    return $arguments;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(ImageInterface|array $arguments, $extra = NULL) {
    assert($arguments instanceof ImageInterface);
    $image = $arguments;
    $arguments = $extra;
    assert(is_array($arguments));

    // PHP installations using non-bundled GD do not have imagefilter.
    if (!function_exists('imagefilter')) {
      $this->logger->notice("The image '@file' could not be desaturated because the imagefilter() function is not available in this PHP installation.", ['@file' => $image->getSource()]);
      return FALSE;
    }

    return imagefilter($image->getGdImageObject(), IMG_FILTER_GRAYSCALE);
  }

}
