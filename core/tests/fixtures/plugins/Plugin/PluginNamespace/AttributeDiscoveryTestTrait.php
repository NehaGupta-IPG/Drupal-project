<?php

declare(strict_types=1);

namespace com\example\PluginNamespace;

/**
 * An trait in a plugin directory that should not be parsed.
 *
 * Attribute discovery should skip all traits in plugin directories. During
 * discovery, PHP files with names that end in "Trait" are excluded.
 */
trait AttributeDiscoveryTestTrait {}
