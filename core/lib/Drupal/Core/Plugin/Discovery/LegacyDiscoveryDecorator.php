<?php

declare(strict_types=1);

namespace Drupal\Core\Plugin\Discovery;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;

/**
 * Discovery decorator which includes a secondary discovery for legacy.
 */
class LegacyDiscoveryDecorator implements DiscoveryInterface {

  use DiscoveryTrait;

  /**
   * Creates a new LegacyDiscoveryDecorator.
   *
   * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $decorated
   *   The parent object implementing DiscoveryInterface that is being
   *   decorated.
   * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $legacy
   *   The discovery object handling plugins in the deprecated location or
   *   format.
   * @param string $deprecatedDiscoveryDetails
   *   A phrase giving details of why a plugin is deprecated, to be used as part
   *   of the deprecation message. For example: 'in the Foo/Bar subdirectory',
   *   or 'in a YAML file'.
   * @param string $updateDetails
   *   A full sentence explaining how to update a deprecated plugin. For
   *   example, 'Move the plugin class to the Bar subdirectory and adjust its
   *   namespace.'.
   * @param string $deprecationVersion
   *   The version in which the deprecation was introduced.
   * @param string $removedVersion
   *   The version in which the backwards-compatibility handling will be
   *   removed.
   * @param string $changeRecordUrl
   *   The URL for the change record.
   */
  public function __construct(
    protected DiscoveryInterface $decorated,
    protected DiscoveryInterface $legacy,
    protected string $deprecatedDiscoveryDetails,
    protected string $updateDetails,
    protected string $deprecationVersion,
    protected string $removedVersion,
    protected string $changeRecordUrl,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $plugin_definitions = $this->decorated->getDefinitions();

    $legacy_plugin_definitions = $this->legacy->getDefinitions();
    foreach ($legacy_plugin_definitions as $plugin_id => $legacy_plugin_definition) {
      // phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorVersion, Drupal.Semantics.FunctionTriggerError.TriggerErrorSeeUrlFormat
      @trigger_error("Plugin discovery of plugin '{$plugin_id}' {$this->deprecatedDiscoveryDetails} is deprecated in {$this->deprecationVersion} and is removed in {$this->removedVersion}. {$this->updateDetails} See {$this->changeRecordUrl}.", E_USER_DEPRECATED);
    }

    return $plugin_definitions + $legacy_plugin_definitions;
  }

  /**
   * Passes through all unknown calls onto the decorated object.
   */
  public function __call($method, $args): mixed {
    return call_user_func_array([$this->decorated, $method], $args);
  }

}
