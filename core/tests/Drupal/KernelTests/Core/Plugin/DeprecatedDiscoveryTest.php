<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Plugin;

use Drupal\Core\Plugin\Discovery\LegacyDiscoveryDecorator;
use Drupal\Core\Plugin\LegacySubdirPluginManagerTrait;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests systems for plugin deprecation.
 */
#[CoversClass(LegacyDiscoveryDecorator::class)]
#[Group('Plugin')]
#[IgnoreDeprecations]
class DeprecatedDiscoveryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'deprecation_plugin_test',
  ];

  /**
   * Tests deprecation of plugin subdir.
   */
  #[CoversClass(LegacySubdirPluginManagerTrait::class)]
  public function testDeprecatedSubdir(): void {
    $plugin_manager = $this->container->get('plugin.manager.deprecation_plugin_test_subdir_deprecation');

    $this->expectDeprecation("Plugin discovery of plugin 'old_plugin' in subdirectory Plugin/SubdirDeprecationLegacy is deprecated in example:12.4.0 and is removed in example:13.0.0. The plugin class should be moved to Plugin/SubdirDeprecationNew and its namespace adjusted accordingly. See http://example.com/change-record/subdir-deprecation.");
    $definitions = $plugin_manager->getDefinitions();

    // Plugins are discovered in both the old and new subdirectories.
    $this->assertArrayHasKey('old_plugin', $definitions);
    $this->assertArrayHasKey('new_plugin', $definitions);
  }

}
