<?php

namespace Drupal\Component\Plugin\Attribute;

/**
 * Attribute class to mark a plugin attribute property as deprecated.
 *
 * When this is set on a base plugin attribute, a third-party attribute is
 * allowed to use the same property name. This allows properties to be moved to
 * third-party attributes while maintaining backwards-compatibility.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class PluginDeprecatedProperty extends AttributeBase {

  /**
   * Constructs a PluginDeprecatedProperty object.
   */
  public function __construct() {}

}
