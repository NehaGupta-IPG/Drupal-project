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
   * Whether to load the stored container even if the module list is set.
   *
   * Only overrides when it's TRUE, FALSE does nothing.
   */
  protected bool $overrideNeedsRebuild = FALSE;

  /**
   * An ID suffix to increment on rebuilds.
   */
  protected int $idSuffix = 0;

  /**
   * Sets whether the container needs to be rebuilt.
   *
   * @param bool $rebuild
   *   TRUE to rebuild the container when it is next initialized, FALSE to
   *   load the stored container if possible.
   */
  public function setContainerNeedsRebuild(bool $rebuild): void {
    $this->containerNeedsRebuild = $rebuild;
  }

  /**
   * Resets the ID suffix to 0.
   */
  public function resetIdSuffix(): void {
    $this->idSuffix = 0;
  }

  /**
   * Sets whether to load the stored container even if the module list is set.
   *
   * @param bool $use
   *   TRUE to load the stored container once when the container is next
   *   initialized, instead of rebuilding it.
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
  protected function getContainerFromStorage(string $container_id): ?ContainerInterface {
    if ($this->overrideNeedsRebuild) {
      // Only override once.
      $this->overrideNeedsRebuild = FALSE;
      return $this->bootstrapContainer->get('container_storage')->load($container_id);
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
