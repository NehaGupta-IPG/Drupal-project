<?php

declare(strict_types = 1);

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
   *   The discovery object handling plugins in the deprecated location.
   * @param string $deprecationMessage
   *   The message to use for the deprecation error. The first '%s' will be
   *   replaced with the plugin ID.
   */
  public function __construct(
    protected DiscoveryInterface $decorated,
    protected DiscoveryInterface $legacy,
    protected string $deprecationMessage,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $plugin_definitions = $this->decorated->getDefinitions();

    $legacy_plugin_definitions = $this->legacy->getDefinitions();
    foreach ($legacy_plugin_definitions as $plugin_id => $legacy_plugin_definition) {
      // phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
      @trigger_error(sprintf($this->deprecationMessage, $plugin_id), E_USER_DEPRECATED);
    }

    return $plugin_definitions + $legacy_plugin_definitions;
  }

  /**
   * Passes through all unknown calls onto the decorated object.
   */
  public function __call($method, $args) {
    return call_user_func_array([$this->decorated, $method], $args);
  }

}
