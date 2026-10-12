<?php

declare(strict_types=1);

namespace Drupal\Core\Test;

use Drupal\Core\Installer\InstallerKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Installer kernel that shares its containers with other tests.
 *
 * Every functional test installs Drupal, and the installer compiles the same
 * containers for every test that installs the same modules. This kernel loads
 * them from the container storage shared by the tests of a run instead.
 *
 * The test framework selects this kernel with the 'kernel_class' installation
 * parameter.
 *
 * @see \Drupal\Core\Test\FunctionalTestSetupTrait::installParameters()
 * @see install_begin_request()
 *
 * @internal
 */
class TestInstallerKernel extends InstallerKernel {

  use SharedContainerKernelTrait {
    getContainerConfiguration as getSharedContainerConfiguration;
  }

  /**
   * {@inheritdoc}
   */
  protected function getContainerFromStorage(string $container_id): ?ContainerInterface {
    $storage = $this->bootstrapContainer->get('container_storage');
    if (!$storage instanceof TestContainerStorage) {
      return parent::getContainerFromStorage($container_id);
    }
    // The parent always compiles the installer container, so this ignores the
    // flags that make it do so.
    return $storage->load($this->getSharedContainerIdentifier(TRUE));
  }

  /**
   * {@inheritdoc}
   */
  protected function compileContainer() {
    $container = parent::compileContainer();
    // The installer does not dump its container, so store it here. The
    // compiled container builder is used for the rest of the request, as in
    // the parent.
    $storage = $this->bootstrapContainer->get('container_storage');
    if ($storage instanceof TestContainerStorage) {
      // The installer removes the compiler pass that replaces aliases with
      // the services they point to. The container builder still resolves a
      // public alias to a private service, but a dumped container does not.
      // @see \Drupal\Core\Installer\NormalInstallerServiceProvider::register()
      foreach ($container->getAliases() as $alias) {
        if ($alias->isPublic() && $container->has((string) $alias)) {
          $container->findDefinition((string) $alias)->setPublic(TRUE);
        }
      }
      $storage->dump($container, $this->getSharedContainerIdentifier(TRUE));
    }
    return $container;
  }

  /**
   * {@inheritdoc}
   */
  protected function getContainerConfiguration(): array {
    global $install_state;
    $configuration = $this->getSharedContainerConfiguration();
    // The installer service providers and the default language depend on the
    // installation state.
    // @see \Drupal\Core\Installer\NormalInstallerServiceProvider::register()
    // @see \Drupal\Core\DrupalKernel::compileContainer()
    $configuration[] = !empty($install_state['config_install_path']);
    $configuration[] = $install_state['parameters']['langcode'] ?? NULL;
    return $configuration;
  }

}
