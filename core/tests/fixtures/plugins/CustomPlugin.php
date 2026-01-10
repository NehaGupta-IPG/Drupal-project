<?php

declare(strict_types=1);

namespace com\example\PluginNamespace;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Custom plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CustomPlugin extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly string $title,
  ) {}

}

/**
 * Custom plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CustomPlugin2 extends Plugin {}
