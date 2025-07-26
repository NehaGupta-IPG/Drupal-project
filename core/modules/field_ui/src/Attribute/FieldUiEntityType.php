<?php

namespace Drupal\field_ui\Attribute;

use Drupal\Component\Plugin\Attribute\PluginExtender;

/**
 * Defines Field UI's extensions to entity type definitions.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FieldUiEntityType extends PluginExtender {

  public function __construct(
    /**
     * The route name used by field UI to attach its management pages.
     */
    public readonly string $field_ui_base_route,
  ) {

  }

}
