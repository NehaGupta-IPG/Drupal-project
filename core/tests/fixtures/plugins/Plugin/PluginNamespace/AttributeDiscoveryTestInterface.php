<?php

declare(strict_types=1);

namespace fixtures\plugins\Plugin\PluginNamespace;

/**
 * An interface in a plugin directory that should not be parsed.
 *
 * Attribute discovery should skip all interfaces in plugin directories. During
 * discovery, PHP files with names that end in "Interface" are excluded.
 */
interface AttributeDiscoveryTestInterface {}
