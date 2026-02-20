<?php

namespace Drupal\image_test\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD2 Scale and crop operation.
 */
#[ImageToolkitOperation(
  id: "gd_stateful_scale_and_crop",
  toolkit: "gd",
  operation: "stateful_scale_and_crop",
  label: new TranslatableMarkup("Test scale and crop"),
  description: new TranslatableMarkup("Test")
)]
class StatefulScaleAndCrop extends GDImageToolkitOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'x' => [
        'description' => 'The horizontal offset for the start of the crop, in pixels',
        'required' => FALSE,
        'default' => NULL,
      ],
      'y' => [
        'description' => 'The vertical offset for the start the crop, in pixels',
        'required' => FALSE,
        'default' => NULL,
      ],
      'width' => [
        'description' => 'The target width, in pixels',
      ],
      'height' => [
        'description' => 'The target height, in pixels',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $actualWidth = $this->getToolkit()->getWidth();
    $actualHeight = $this->getToolkit()->getHeight();

    $scaleFactor = max($arguments['width'] / $actualWidth, $arguments['height'] / $actualHeight);

    $arguments['x'] = isset($arguments['x']) ?
      (int) round($arguments['x']) :
      (int) round(($actualWidth * $scaleFactor - $arguments['width']) / 2);
    $arguments['y'] = isset($arguments['y']) ?
      (int) round($arguments['y']) :
      (int) round(($actualHeight * $scaleFactor - $arguments['height']) / 2);
    $arguments['resize'] = [
      'width' => (int) round($actualWidth * $scaleFactor),
      'height' => (int) round($actualHeight * $scaleFactor),
    ];

    // Fail when width or height are 0 or negative.
    if ($arguments['width'] <= 0) {
      throw new \InvalidArgumentException("Invalid width ('{$arguments['width']}') specified for the image 'scale_and_crop' operation");
    }
    if ($arguments['height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ('{$arguments['height']}') specified for the image 'scale_and_crop' operation");
    }

    return $arguments;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments = []) {
    return $this->getToolkit()->apply('resize', $arguments['resize'])
        && $this->getToolkit()->apply('crop', $arguments);
  }

}
