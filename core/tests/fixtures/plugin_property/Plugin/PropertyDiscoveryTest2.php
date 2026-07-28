<?php

declare(strict_types=1);

namespace com\example\PluginProperty\Plugin;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * A plugin without any property attributes.
 */
#[Plugin(
  id: 'property_discovery_test_2',
)]
class PropertyDiscoveryTest2 {}
