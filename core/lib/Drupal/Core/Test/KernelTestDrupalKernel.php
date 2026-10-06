<?php

declare(strict_types=1);

namespace Drupal\Core\Test;

use Drupal\Core\DrupalKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sublass of DrupalKernel for kernel testing.
 *
 * This class extends the DrupalKernel to allow re-use of the dependency
 * injection container between kernel test methods.
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
    if ($this->containerNeedsRebuild) {
      $this->cacheSuffix++;
    }
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
