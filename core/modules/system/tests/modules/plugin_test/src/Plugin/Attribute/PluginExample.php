<?php

declare(strict_types=1);

namespace Drupal\plugin_test\Plugin\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines a custom PluginExample attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class PluginExample extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?string $custom = NULL,
  ) {}

}
