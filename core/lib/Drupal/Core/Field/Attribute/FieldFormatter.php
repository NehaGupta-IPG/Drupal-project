<?php

declare(strict_types=1);

namespace Drupal\Core\Field\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a FieldFormatter attribute for plugin discovery.
 *
 * Formatters handle the display of field values. They are typically
 * instantiated and invoked by an EntityDisplay object.
 *
 * Additional attribute keys for formatters can be defined in
 * hook_field_formatter_info_alter().
 *
 * @see \Drupal\Core\Field\FormatterPluginManager
 * @see \Drupal\Core\Field\FormatterInterface
 *
 * @ingroup field_formatter
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FieldFormatter extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly array $field_types = [],
    public readonly ?int $weight = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
