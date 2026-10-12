<?php

declare(strict_types=1);

namespace Drupal\Core\Test;

use Drupal\Core\DrupalKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Subclass of DrupalKernel for kernel testing.
 *
 * This class extends the DrupalKernel to allow re-use of the dependency
 * injection container between kernel test methods.
 *
 * @internal only use in tests.
 */
class KernelTestDrupalKernel extends DrupalKernel {

  /**
   * Whether the container should be retrieved from che.
   *
   * Only overrides when it's TRUE, FALSE does nothing.
   */
  protected bool $overrideNeedsRebuild = FALSE;

  /**
   * An ID suffix to increment on rebuilds.
   */
  protected int $idSuffix = 0;

  /**
   * Sets whether to override rebuilding the container.
   *
   * @param bool $rebuild
   *   If TRUE, the container will not be rebuilt, if FALSE, defaults to the
   *   standard behavior.
   */
  public function setContainerNeedsRebuild(bool $rebuild): void {
    $this->containerNeedsRebuild = $rebuild;
  }

  /**
   * Resets the id suffix to 0.
   */
  public function resetIdSuffix(): void {
    $this->idSuffix = 0;
  }

  /**
   * Sets whether the container should be rebuilt.
   */
  public function setOverrideNeedsRebuild(bool $use): void {
    $this->overrideNeedsRebuild = $use;
  }

  /**
   * {@inheritdoc}
   */
  protected function getContainerIdentifier(): string {
    $identifier = parent::getContainerIdentifier();
    // Vary by the suffix, so that a container rebuilt during a test method is
    // written under its own identifier and does not overwrite the initial
    // container that the other test methods of the class load.
    $identifier .= ':' . $this->idSuffix;
    return $identifier;
  }

  /**
   * {@inheritdoc}
   */
  protected function getContainerFromStorage($container_id): ?ContainerInterface {
    if ($this->overrideNeedsRebuild) {
      // Only override once.
      $this->overrideNeedsRebuild = FALSE;
      $storage = $this->bootstrapContainer->get('container_storage');
      $container = $storage->load($container_id);
      return $container;
    }
    return parent::getContainerFromStorage($container_id);
  }

  /**
   * {@inheritdoc}
   */
  public function rebuildContainer() {
    $this->idSuffix++;
    return parent::rebuildContainer();
  }

  /**
   * {@inheritdoc}
   */
  public function resetContainer(): ContainerInterface {
    $this->idSuffix++;
    return parent::resetContainer();
  }

}
