<?php

declare(strict_types=1);

namespace Drupal\rest\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a REST resource attribute object.
 *
 * Plugin Namespace: Plugin\rest\resource
 *
 * For a working example, see \Drupal\dblog\Plugin\rest\resource\DbLogResource
 *
 * @see \Drupal\rest\Plugin\Type\ResourcePluginManager
 * @see \Drupal\rest\Plugin\ResourceBase
 * @see \Drupal\rest\Plugin\ResourceInterface
 * @see plugin_api
 *
 * @ingroup third_party
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class RestResource extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?string $serialization_class = NULL,
    public readonly ?string $deriver = NULL,
    public readonly array $uri_paths = [],
  ) {}

}
