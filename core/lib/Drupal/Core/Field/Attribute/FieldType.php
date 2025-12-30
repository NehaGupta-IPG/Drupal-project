<?php

declare(strict_types=1);

namespace Drupal\Core\Field\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a FieldType attribute.
 *
 * Additional attribute keys for field types can be defined in
 * hook_field_info_alter().
 *
 * @ingroup field_types
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FieldType extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup|array|null $description = NULL,
    public readonly string $category = '',
    public readonly int $weight = 0,
    public readonly ?string $default_widget = NULL,
    public readonly ?string $default_formatter = NULL,
    public readonly bool $no_ui = FALSE,
    public readonly ?string $list_class = NULL,
    public readonly ?int $cardinality = NULL,
    public readonly array $constraints = [],
    public readonly array $config_dependencies = [],
    public readonly array $column_groups = [],
    public readonly array $serialized_property_names = [],
    public readonly ?string $deriver = NULL,
    public readonly ?string $module = NULL,
  ) {
  }

}
