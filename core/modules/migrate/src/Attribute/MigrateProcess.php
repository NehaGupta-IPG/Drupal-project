<?php

declare(strict_types=1);

namespace Drupal\migrate\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines a MigrateProcess attribute.
 *
 * Plugin Namespace: Plugin\migrate\process
 *
 * For a working example, see
 * \Drupal\migrate\Plugin\migrate\process\DefaultValue
 *
 * @see \Drupal\migrate\Plugin\MigratePluginManager
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 * @see \Drupal\migrate\ProcessPluginBase
 * @see \Drupal\migrate\Attribute\MigrateDestination
 * @see \Drupal\migrate\Attribute\MigrateSource
 * @see plugin_api
 *
 * @ingroup migration
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MigrateProcess extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly bool $handle_multiples = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
