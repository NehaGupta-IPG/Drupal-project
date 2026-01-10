<?php

declare(strict_types=1);

namespace Drupal\Core\ImageToolkit\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Plugin attribute for the image toolkit plugin.
 *
 * An image toolkit operation plugin provides a self-contained image
 * manipulation routine, for a specific image toolkit. Examples of image
 * toolkit operations are scaling, cropping, rotating, etc.
 *
 * Plugin namespace: Plugin\ImageToolkit\Operation
 *
 * For a working example, see
 * \Drupal\system\Plugin\ImageToolkit\Operation\gd\Crop
 *
 * @see \Drupal\Core\ImageToolkit\Attribute\ImageToolkit
 * @see \Drupal\image\Attribute\ImageEffect
 * @see \Drupal\Core\ImageToolkit\ImageToolkitOperationInterface
 * @see \Drupal\Core\ImageToolkit\ImageToolkitOperationBase
 * @see \Drupal\Core\ImageToolkit\ImageToolkitOperationManager
 * @see plugin_api
 *
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ImageToolkitOperation extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly string $toolkit,
    public readonly string $operation,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
