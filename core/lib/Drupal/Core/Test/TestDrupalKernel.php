<?php

declare(strict_types=1);

namespace Drupal\Core\Test;

use Drupal\Core\DrupalKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Kernel of a test that shares its containers with other tests.
 *
 * Tests compile the same containers many times, because each test builds its
 * own site. When the container storage is shared by the tests of a run, this
 * kernel loads a container that another test compiled for the same modules,
 * when it boots and when the module list changes. It stores the containers
 * that it compiles in these cases for other tests to load.
 *
 * Containers are also stored under the identifier of the site, as by any
 * other kernel, so that requests to the site of a functional test find them.
 * Containers that are rebuilt for other reasons are only stored for the site,
 * if the kernel allows dumping, because the test may have changed something
 * that the shared identifier does not cover.
 *
 * A test that registers itself as a service provider implements
 * \Drupal\Core\Test\SharedContainerTestInterface.
 *
 * @see \Drupal\Core\Test\TestContainerStorage
 * @see \Drupal\Core\Test\FunctionalTestSetupTrait::initKernel()
 * @see \Drupal\KernelTests\KernelTestBase::bootKernel()
 *
 * @internal
 */
class TestDrupalKernel extends DrupalKernel {

  use SharedContainerKernelTrait;

  /**
   * Whether the container is being initialized for a new module list.
   */
  protected bool $updatingModules = FALSE;

  /**
   * Whether the container that is being initialized can be shared.
   */
  protected bool $sharingContainer = FALSE;

  /**
   * The shared identifier of the container that is being initialized.
   */
  protected ?string $sharedContainerId = NULL;

  /**
   * Whether the container that is being initialized was compiled.
   */
  protected bool $compiled = FALSE;

  /**
   * {@inheritdoc}
   */
  public function updateModules(array $module_list, array $module_filenames = []): void {
    $this->updatingModules = TRUE;
    try {
      parent::updateModules($module_list, $module_filenames);
    }
    finally {
      $this->updatingModules = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeContainer() {
    $storage = $this->getSharedContainerStorage();
    // The kernel shares the container it boots with, and the containers for
    // new module lists. It does not share explicitly rebuilt containers.
    $this->sharingContainer = $storage && (!$this->booted || $this->updatingModules || !$this->containerNeedsRebuild);
    $this->sharedContainerId = NULL;
    $this->compiled = FALSE;
    $allow_dumping = $this->allowDumping;
    if ($this->sharingContainer) {
      $this->allowDumping = TRUE;
    }

    try {
      $container = parent::initializeContainer();
    }
    finally {
      $this->allowDumping = $allow_dumping;
    }

    if ($storage) {
      if ($this->sharingContainer && $this->compiled) {
        // Share the container that was just stored for the site.
        $storage->addIdentifier($this->getSharedContainerId(), $this->getContainerIdentifier());
      }
      // A test that registers services on the container builder does not
      // get the container that was loaded instead.
      foreach ($GLOBALS['conf']['container_service_providers'] ?? [] as $provider) {
        if ($provider instanceof SharedContainerTestInterface) {
          $provider->setContainer($container);
        }
      }
    }
    return $container;
  }

  /**
   * {@inheritdoc}
   */
  protected function getContainerFromStorage(string $container_id): ?ContainerInterface {
    $container = parent::getContainerFromStorage($container_id);
    if ($container || !$this->sharingContainer) {
      return $container;
    }
    $storage = $this->getSharedContainerStorage();
    $container = $storage->load($this->getSharedContainerId());
    if ($container) {
      // Requests to the site look the container up by the site identifier.
      $storage->addIdentifier($container_id, $this->getSharedContainerId());
    }
    return $container;
  }

  /**
   * Returns the shared identifier of the container that is being initialized.
   *
   * @return string
   *   The shared container identifier.
   */
  protected function getSharedContainerId(): string {
    return $this->sharedContainerId ??= $this->getSharedContainerIdentifier();
  }

  /**
   * {@inheritdoc}
   */
  protected function compileContainer() {
    $this->compiled = TRUE;
    return parent::compileContainer();
  }

}
