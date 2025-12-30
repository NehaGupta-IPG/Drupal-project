<?php

declare(strict_types=1);

namespace Drupal\migrate\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines a MigrateDestination attribute.
 *
 * Plugin Namespace: Plugin\migrate\destination
 *
 * For a working example, see
 * \Drupal\migrate\Plugin\migrate\destination\UrlAlias
 *
 * @see \Drupal\migrate\Plugin\MigrateDestinationPluginManager
 * @see \Drupal\migrate\Plugin\MigrateDestinationInterface
 * @see \Drupal\migrate\Plugin\migrate\destination\DestinationBase
 * @see \Drupal\migrate\Attribute\MigrateProcess
 * @see \Drupal\migrate\Attribute\MigrateSource
 * @see plugin_api
 *
 * @ingroup migration
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MigrateDestination extends Plugin {

  public function __construct(
    public readonly string $id,
    public bool $requirements_met = TRUE,
    public readonly ?string $destination_module = NULL,
    public readonly ?string $deriver = NULL,
  ) {
  }

}
