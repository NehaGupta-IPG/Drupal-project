<?php

declare(strict_types=1);

namespace Drupal\Core\Test;

use Drupal\Core\DrupalKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Kernel of a functional test that shares its containers with other tests.
 *
 * A functional test compiles the container of the site it installed, and
 * compiles it again whenever it installs modules. When the container storage
 * is shared by the tests of a run, this kernel loads a container that another
 * test compiled for the same modules instead. It also stores the container
 * under the identifier of the site, so that the requests of the test to its
 * site load it.
 *
 * @see \Drupal\Core\Test\FunctionalTestSetupTrait::initKernel()
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
  protected function getContainerFromStorage(string $container_id): ?ContainerInterface {
    $container = parent::getContainerFromStorage($container_id);
    $storage = $this->bootstrapContainer->get('container_storage');
    if ($container || !$storage instanceof TestContainerStorage || !$this->sharesContainer()) {
      return $container;
    }
    $shared_id = $this->getSharedContainerIdentifier(TRUE);
    $container = $storage->load($shared_id);
    if ($container) {
      // Requests to the site look the container up by the site identifier.
      $storage->addIdentifier($container_id, $shared_id);
    }
    return $container;
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeContainer() {
    $shares_container = $this->sharesContainer();
    $this->compiled = FALSE;
    $container = parent::initializeContainer();
    $storage = $this->bootstrapContainer->get('container_storage');
    if ($this->compiled && $shares_container && $this->allowDumping && $storage instanceof TestContainerStorage) {
      // Share the container that was just stored under the site identifier.
      $storage->addIdentifier($this->getSharedContainerIdentifier(TRUE), $this->getContainerIdentifier());
    }
    return $container;
  }

  /**
   * {@inheritdoc}
   */
  protected function compileContainer() {
    $this->compiled = TRUE;
    return parent::compileContainer();
  }

  /**
   * Returns whether the container that is being initialized can be shared.
   *
   * @return bool
   *   TRUE if the module list is known. An explicit rebuild of the container
   *   is not shared, because the test may have changed something that the
   *   identifier does not cover.
   */
  protected function sharesContainer(): bool {
    return !$this->containerNeedsRebuild || $this->updatingModules;
  }

}
