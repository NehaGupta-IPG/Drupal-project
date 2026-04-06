<?php

namespace Drupal\Core\Plugin\Discovery;

use Drupal\Component\Plugin\Discovery\DerivativeDiscoveryDecorator;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Injects dependencies into derivers if they use ContainerDeriverInterface.
 *
 * @see \Drupal\Core\Plugin\Discovery\ContainerDeriverInterface
 */
class ContainerDerivativeDiscoveryDecorator extends DerivativeDiscoveryDecorator implements CacheableDependencyInterface {

  /**
   * Cacheability metadata collected from the derivers.
   */
  protected CacheableMetadata $cacheability;

  public function __construct(mixed ...$arguments) {
    parent::__construct(...$arguments);
    $this->cacheability = new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->cacheability->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->cacheability->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->cacheability->getCacheMaxAge();
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeriver($base_plugin_id, $base_definition) {
    if (!isset($this->derivers[$base_plugin_id])) {
      $this->derivers[$base_plugin_id] = FALSE;
      $class = $this->getDeriverClass($base_definition);
      if ($class) {
        // If the deriver provides a factory method, pass the container to it.
        if (is_subclass_of($class, '\Drupal\Core\Plugin\Discovery\ContainerDeriverInterface')) {
          /** @var \Drupal\Core\Plugin\Discovery\ContainerDeriverInterface $class */
          $deriver = $class::create(\Drupal::getContainer(), $base_plugin_id);
        }
        else {
          $deriver = new $class($base_plugin_id);
        }

        if ($deriver instanceof CacheableDependencyInterface) {
          $this->cacheability->addCacheableDependency($deriver);
        }
        $this->derivers[$base_plugin_id] = $deriver;
      }
    }
    return $this->derivers[$base_plugin_id] ?: NULL;
  }

}
