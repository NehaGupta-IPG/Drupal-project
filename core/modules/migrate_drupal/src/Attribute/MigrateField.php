<?php

declare(strict_types=1);

namespace Drupal\migrate_drupal\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines a field plugin attribute object.
 *
 * Field plugins are responsible for handling the migration of custom fields
 * (provided by Field API in Drupal 7) to Drupal 8+. They are allowed to alter
 * fieldable entity migrations when these migrations are being generated, and
 * can compute destination field types for individual fields during the actual
 * migration process.
 *
 * Plugin Namespace: Plugin\migrate\field
 *
 * For a working example, see
 * \Drupal\datetime\Plugin\migrate\field\DateField
 *
 * @see \Drupal\migrate\Plugin\MigratePluginManager
 * @see \Drupal\migrate_drupal\Plugin\MigrateFieldInterface;
 * @see \Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase
 * @see plugin_api
 *
 * @ingroup migration
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MigrateField extends Plugin {

  /**
   * The plugin definition.
   *
   * @var array
   */
  protected $definition;

  public function __construct(
    public readonly string $id,
    public readonly array $core = [6],
    public readonly int $weight = 0,
    public readonly array $type_map = [],
    public readonly ?string $source_module = NULL,
    public readonly ?string $destination_module = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
