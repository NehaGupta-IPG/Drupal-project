<?php

declare(strict_types=1);

namespace Drupal\image\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an ImageEffect attribute for plugin discovery.
 *
 * Plugin Namespace: Plugin\ImageEffect
 *
 * For a working example, see
 * \Drupal\image\Plugin\ImageEffect\ResizeImageEffect
 *
 * @see hook_image_effect_info_alter()
 * @see \Drupal\image\ConfigurableImageEffectInterface
 * @see \Drupal\image\ConfigurableImageEffectBase
 * @see \Drupal\image\ImageEffectInterface
 * @see \Drupal\image\ImageEffectBase
 * @see \Drupal\image\ImageEffectManager
 * @see \Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ImageEffect extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
