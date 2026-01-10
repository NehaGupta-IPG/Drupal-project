<?php

namespace Drupal\Core\Plugin\Discovery;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;

/**
 * Allows info hook implementations to enhance discovered plugin definitions.
 */
class InfoHookDecorator implements DiscoveryInterface {

  use DiscoveryTrait;

  /**
   * The Discovery object being decorated.
   *
   * @var \Drupal\Component\Plugin\Discovery\DiscoveryInterface
   */
  protected $decorated;

  /**
   * The name of the info hook that will be implemented.
   *
   * @var string
   */
  protected $hook;

  public function __construct(DiscoveryInterface $decorated, $hook) {
    $this->decorated = $decorated;
    $this->hook = $hook;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = $this->decorated->getDefinitions();
    \Drupal::moduleHandler()->invokeAllWith(
      $this->hook,
      function (callable $hook, string $module) use (&$definitions) {
        $hook($definitions);
      }
    );
    return $definitions;
  }

  /**
   * Passes through all unknown calls onto the decorated object.
   */
  public function __call($method, $args) {
    return call_user_func_array([$this->decorated, $method], $args);
  }

}
