<?php

namespace Drupal\deprecation_plugin_test\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a SubdirDeprecation attribute object.
 *
 * Plugin namespace: SubdirDeprecationNew.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SubdirDeprecation extends Plugin {

  /**
   * Constructs a SubdirDeprecation attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The plugin label.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
  ) {
  }

}
