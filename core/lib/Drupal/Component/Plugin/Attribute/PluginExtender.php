<?php

namespace Drupal\Component\Plugin\Attribute;

/**
 * Base attribute class for third-party extensions to plugin definitions.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class PluginExtender extends AttributeBase {

  /**
   * Constructs a plugin extender object.
   */
  public function __construct() {}

}
