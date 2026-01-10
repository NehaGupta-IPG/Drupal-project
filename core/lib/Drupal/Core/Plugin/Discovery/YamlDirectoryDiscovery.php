<?php

namespace Drupal\Core\Plugin\Discovery;

use Drupal\Component\Discovery\YamlDirectoryDiscovery as ComponentYamlDirectoryDiscovery;

/**
 * Allows multiple YAML files per directory to define plugin definitions.
 */
class YamlDirectoryDiscovery extends YamlDiscovery {

  public function __construct(array $directories, $file_cache_key_suffix, $key = 'id') {
    // Intentionally does not call parent constructor as this class uses a
    // different YAML discovery.
    $this->discovery = new ComponentYamlDirectoryDiscovery($directories, $file_cache_key_suffix, $key);
  }

}
