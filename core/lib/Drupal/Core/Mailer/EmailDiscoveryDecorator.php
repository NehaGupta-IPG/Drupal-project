<?php

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;

/**
 * Turns discovered plugin array definitions into EmailDefinition objects.
 */
class EmailDiscoveryDecorator implements DiscoveryInterface {

  use DiscoveryTrait;

  public function __construct(protected readonly DiscoveryInterface $decorated) {
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions(): array {
    $definitions = $this->decorated->getDefinitions();
    foreach ($definitions as $key => $definition) {
      $definitions[$key] = is_array($definition) ? new EmailDefinition($definition) : $definition;
    }
    return $definitions;
  }

  /**
   * Passes through all unknown calls onto the decorated object.
   */
  public function __call($method, $args): mixed {
    return call_user_func_array([$this->decorated, $method], $args);
  }

}
