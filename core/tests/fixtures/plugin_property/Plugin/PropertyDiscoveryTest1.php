<?php

declare(strict_types=1);

namespace com\example\PluginProperty\Plugin;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Tests\Component\Plugin\Attribute\NotAProperty;
use Drupal\Tests\Component\Plugin\Attribute\OptionalProperty;

/**
 * A plugin with property attributes from an optional provider.
 *
 * The attribute classes are declared in
 * \Drupal\Tests\Component\Plugin\Attribute\AttributeClassDiscoveryPluginPropertyTest.
 *
 * \com\missing\Attribute\NeverAvailable is not declared anywhere. Reflecting on
 * this class must not fail because of it.
 */
#[Plugin(
  id: 'property_discovery_test_1',
)]
#[OptionalProperty('optional_property', 'optional value')]
#[OptionalProperty(key: 'second_optional_property', value: TRUE)]
#[NotAProperty('This attribute does not implement PluginPropertyInterface.')]
#[\com\missing\Attribute\NeverAvailable('never', 'available')]
class PropertyDiscoveryTest1 {}
