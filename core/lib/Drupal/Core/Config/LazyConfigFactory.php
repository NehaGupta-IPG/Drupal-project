<?php

namespace Drupal\Core\Config;

/**
 * Defines the lazy configuration object factory.
 *
 * The lazy configuration object factory ('config.factory.lazy' service) is a
 * drop-in replacement for the 'config.factory' service, which lazily loads the
 * 'config.factory' service if/when it is needed and forwards the method call.
 *
 * Services can inject 'config.factory.lazy' to work around a circular reference
 * that appears if a configuration storage dependency (such as a cache backend)
 * requires a service that depends on configuration storage (such as a logger).
 *
 * @see \Drupal\Core\Config\ConfigFactory
 *
 * @ingroup config_api
 */
class LazyConfigFactory implements ConfigFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(protected ConfigFactoryInterface $configFactory) {
  }

  /**
   * {@inheritdoc}
   */
  public function get($name): ImmutableConfig {
    return $this->configFactory->get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getEditable($name): Config {
    return $this->configFactory->getEditable($name);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $names): array {
    return $this->configFactory->loadMultiple($names);
  }

  /**
   * {@inheritdoc}
   */
  public function reset($name = NULL): static {
    $this->configFactory->reset($name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($old_name, $new_name): static {
    $this->configFactory->rename($old_name, $new_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheKeys(): array {
    return $this->configFactory->getCacheKeys();
  }

  /**
   * {@inheritdoc}
   */
  public function clearStaticCache(): static {
    $this->configFactory->clearStaticCache();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = ''): array {
    return $this->configFactory->listAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function addOverride(ConfigFactoryOverrideInterface $config_factory_override): void {
    $this->configFactory->addOverride($config_factory_override);
  }

}
