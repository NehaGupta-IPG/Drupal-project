<?php

declare(strict_types=1);

namespace Drupal\Core\Test;

use Drupal\Core\DrupalKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The DrupalKernel class is the core of Drupal itself.
 *
 * This class is responsible for building the Dependency Injection Container and
 * also deals with the registration of service providers. It allows registered
 * service providers to add their services to the container. Core provides the
 * CoreServiceProvider, which, in addition to registering any core services that
 * cannot be registered in the core.services.yml file, adds any compiler passes
 * needed by core, e.g. for processing tagged services. Each module can add its
 * own service provider, i.e. a class implementing
 * Drupal\Core\DependencyInjection\ServiceProvider, to register services to the
 * container, or modify existing services.
 */
class KernelTestDrupalKernel extends DrupalKernel {

  /**
   * Whether the container should be retrieved from cache.
   *
   * Only overrides when it's TRUE, FALSE does nothing.
   *
   * @internal only use in tests.
   */
  protected bool $overrideUseCache = FALSE;

  /**
   * A cache suffix to increment on rebuilds.
   *
   * @internal only use in tests.
   */
  protected int $cacheSuffix = 0;

  /**
   * Sets whether the container should be rebuilt.
   *
   * @internal only use this in tests.
   */
  public function setContainerNeedsRebuild(bool $rebuild): void {
    $this->containerNeedsRebuild = $rebuild;
  }

  /**
   * Resets the cache suffix to 0.
   *
   * @internal only use this in tests.
   */
  public function resetCacheSuffix(): void {
    $this->cacheSuffix = 0;
  }

  /**
   * Sets whether the container should be retrieved from cache.
   *
   * @internal only use this in tests.
   */
  public function setOverrideUseCache(bool $use): void {
    $this->overrideUseCache = $use;
  }

  /**
   * {@inheritdoc}
   */
  protected function getContainerCacheKey() {
    $key = parent::getContainerCacheKey();
    // Vary by whether cache usage is being overridden, so that when the
    // container is rebuilt with the same key after an override, it does not
    // invalidate the original cached container.
    $key .= ':' . $this->cacheSuffix;
    return $key;
  }

  /**
   * {@inheritdoc}
   */
  protected function getContainerFromCacheIfCacheable(): ?array {
    $container_definition = NULL;
    // If the module list hasn't already been set in updateModules and we are
    // not forcing a rebuild, then try and load the container from the cache.
    if ((empty($this->moduleList) && !$this->containerNeedsRebuild) || $this->overrideUseCache) {
      $container_definition = $this->getCachedContainerDefinition();
    }
    // Only override once.
    if ($this->overrideUseCache) {
      $this->overrideUseCache = FALSE;
    }
    return $container_definition;
  }

  /**
   * {@inheritdoc}
   */
  public function rebuildContainer() {
    $this->cacheSuffix++;
    return parent::rebuildContainer();
  }

  /**
   * {@inheritdoc}
   */
  public function resetContainer(): ContainerInterface {
    $this->cacheSuffix++;
    return parent::resetContainer();
  }

}
