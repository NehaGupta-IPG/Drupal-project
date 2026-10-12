<?php

declare(strict_types=1);

namespace Drupal\Core\Test;

use Drupal\Core\DependencyInjection\ServiceProviderInterface;
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

  use SharedContainerKernelTrait;

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
   * The test whose container is shared with other tests, if any.
   */
  protected ?ServiceProviderInterface $sharedContainerTest = NULL;

  /**
   * Returns a key for the services that the test adds to the container.
   */
  protected ?\Closure $sharedContainerTestKey = NULL;

  /**
   * Passes each new container to the test.
   */
  protected ?\Closure $setTestContainer = NULL;

  /**
   * Whether containers rebuilt after the kernel booted are stored.
   */
  protected bool $allowDumpingAfterBoot = FALSE;

  /**
   * Whether the kernel booted with a shared container.
   */
  protected bool $sharedContainerBooted = FALSE;

  /**
   * Whether the container is being initialized for a new module list.
   */
  protected bool $updatingModules = FALSE;

  /**
   * Shares the containers that this kernel compiles with other tests.
   *
   * Containers are stored in a directory that all tests in a run share. Tests
   * with the same container identifier load the stored container instead of
   * compiling it. The container the kernel boots with is shared, and so are
   * the containers for the module lists that the test changes to later. Other
   * rebuilt containers are only stored for this process, because the test may
   * have changed something that the identifier does not cover.
   *
   * @param string $directory
   *   The directory to store containers in.
   * @param \Drupal\Core\DependencyInjection\ServiceProviderInterface $test
   *   The test, which is registered as a service provider.
   * @param \Closure $test_key
   *   Returns a key that changes whenever the services that the test adds to
   *   the container change.
   * @param \Closure $set_test_container
   *   Passes a new container to the test. The test registers services on the
   *   container builder, which is not used when a stored container is loaded.
   * @param bool $store
   *   Whether to store the container the kernel boots with if it is compiled.
   *   Its identifier does not cover configuration and state, so it can only
   *   be stored when it is compiled with an empty database.
   */
  public function shareContainer(string $directory, ServiceProviderInterface $test, \Closure $test_key, \Closure $set_test_container, bool $store): void {
    $this->sharedContainerTest = $test;
    $this->sharedContainerTestKey = $test_key;
    $this->setTestContainer = $set_test_container;
    $this->allowDumpingAfterBoot = $this->allowDumping;
    $this->allowDumping = $store;
    $this->defaultBootstrapContainerDefinition['services']['container_storage'] = TestContainerStorage::getServiceDefinition($directory);
  }

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
    if ($this->updatingModules) {
      return $this->getSharedContainerIdentifier(TRUE);
    }
    if ($this->sharedContainerBooted) {
      // A rebuilt container is only loaded again by the process that stored
      // it, so it must not be found by any other.
      return 'private:' . getmypid() . ':' . $this->idSuffix;
    }
    if ($this->sharedContainerTest) {
      return $this->getSharedContainerIdentifier();
    }
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
  protected function getServiceProviderKey(string|object $provider): string {
    if ($provider === $this->sharedContainerTest) {
      return ($this->sharedContainerTestKey)();
    }
    return is_object($provider) ? $provider::class : $provider;
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeContainer() {
    $container = parent::initializeContainer();
    if ($this->sharedContainerTest) {
      ($this->setTestContainer)($container);
      if (!$this->sharedContainerBooted) {
        // Containers that are rebuilt later are stored in the same way as
        // without sharing, unless the module list changes.
        $this->sharedContainerBooted = TRUE;
        $this->allowDumping = $this->allowDumpingAfterBoot;
      }
    }
    return $container;
  }

  /**
   * {@inheritdoc}
   */
  protected function getContainerFromStorage(string $container_id): ?ContainerInterface {
    if ($this->updatingModules) {
      return $this->bootstrapContainer->get('container_storage')->load($container_id);
    }
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
  public function updateModules(array $module_list, array $module_filenames = []): void {
    if (!$this->sharedContainerBooted) {
      parent::updateModules($module_list, $module_filenames);
      return;
    }
    // Share the container for the new module list with other tests.
    $allow_dumping = $this->allowDumping;
    $this->updatingModules = TRUE;
    $this->allowDumping = TRUE;
    try {
      parent::updateModules($module_list, $module_filenames);
    }
    finally {
      $this->updatingModules = FALSE;
      $this->allowDumping = $allow_dumping;
    }
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
