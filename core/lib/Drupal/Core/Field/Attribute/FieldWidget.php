<?php

namespace Drupal\Core\Field\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a FieldWidget attribute for plugin discovery.
 *
 * Plugin Namespace: Plugin\Field\FieldWidget
 *
 * Widgets handle how fields are displayed in edit forms.
 *
 * Additional attribute keys for widgets can be defined in
 * hook_field_widget_info_alter().
 *
 * @see \Drupal\Core\Field\WidgetPluginManager
 * @see \Drupal\Core\Field\WidgetInterface
 *
 * @ingroup field_widget
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FieldWidget extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly array $field_types = [],
    public readonly bool $multiple_values = FALSE,
    public readonly ?int $weight = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
