<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Plugin;

use Drupal\Component\Plugin\Discovery\StaticDiscovery;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * A plugin manager for condition plugins.
 */
class TestPluginManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, array $definitions, ?ModuleHandlerInterface $module_handler = NULL, $alter_hook = NULL, $interface = NULL) {
    // Create the object that can be used to return definitions for all the
    // plugins available for this type. Most real plugin managers use a richer
    // discovery implementation, but StaticDiscovery lets us add some simple
    // mock plugins for unit testing.
    $this->discovery = new StaticDiscovery();
    $this->factory = new DefaultFactory($this->discovery, $interface);

    // Add the static definitions.
    foreach ($definitions as $key => $definition) {
      $this->discovery->setDefinition($key, $definition);
    }

    $this->moduleHandler = $module_handler;

    if ($alter_hook) {
      $this->alterInfo($alter_hook);
    }
  }

}
