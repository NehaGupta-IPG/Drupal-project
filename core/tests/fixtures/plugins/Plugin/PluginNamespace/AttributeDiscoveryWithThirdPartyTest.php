<?php

declare(strict_types=1);

namespace com\example\PluginNamespace;

/**
 * Provides a custom test plugin.
 */
#[CustomPlugin(
  id: "discovery_test_1",
  title: "Discovery test plugin",
  third_party_property: 'original',
)]
#[CustomPluginThirdParty(
  // This override is allowed because third_party_property is declared as
  // deprecated on the CustomPlugin attribute.
  third_party_property: 'override',
)]
class AttributeDiscoveryWithThirdPartyTest {}
