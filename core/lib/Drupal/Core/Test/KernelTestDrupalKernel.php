<?php

declare(strict_types=1);

namespace Drupal\Core\Test;

use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Language\Language;
use Drupal\Core\Site\Settings;
use Drupal\DrupalInstalled;
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
   * The test whose container is shared with other tests, if any.
   */
  protected ?ServiceProviderInterface $sharedContainerTest = NULL;

  /**
   * A key for the services that the test adds to the container.
   */
  protected string $sharedContainerTestKey = '';

  /**
   * Whether containers rebuilt after the kernel booted are stored.
   */
  protected bool $allowDumpingAfterBoot = FALSE;

  /**
   * Whether the kernel booted with a shared container.
   */
  protected bool $sharedContainerBooted = FALSE;

  /**
   * Shares the container that this kernel boots with with other tests.
   *
   * The container is stored in a directory that all tests in a run share.
   * Tests with the same container identifier load the stored container instead
   * of compiling it. Only the container the kernel boots with is shared:
   * containers rebuilt later are only stored for this process, because the
   * test may have changed something that the identifier does not cover.
   *
   * @param string $directory
   *   The directory to store the container in.
   * @param \Drupal\Core\DependencyInjection\ServiceProviderInterface $test
   *   The test, which is registered as a service provider.
   * @param string $test_key
   *   A key that changes whenever the services that the test adds to the
   *   container change.
   * @param bool $store
   *   Whether to store the container if it is compiled. Compiling reads
   *   configuration and state, which the identifier does not cover, so only a
   *   container compiled with an empty database can be stored.
   */
  public function shareContainer(string $directory, ServiceProviderInterface $test, string $test_key, bool $store): void {
    $this->sharedContainerTest = $test;
    $this->sharedContainerTestKey = $test_key;
    $this->allowDumpingAfterBoot = $this->allowDumping;
    $this->allowDumping = $store;
    $this->defaultBootstrapContainerDefinition['services']['container_storage'] = [
      'class' => TestContainerStorage::class,
      'arguments' => [
        $directory,
        '@settings',
        [
          'app.root' => '%app.root%',
          'site.path' => '%site.path%',
        ],
      ],
    ];
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
   * Returns the identifier of a container that is shared with other tests.
   *
   * The identifier covers what the compiled container depends on, except for
   * configuration and state: a shared container is only compiled with an
   * empty database. The site path, which differs for every test, is not part
   * of it either: the container storage sets it when it loads the container.
   *
   * @return string
   *   The container identifier.
   */
  protected function getSharedContainerIdentifier(): string {
    $this->discoverServiceProviders();

    // Service files from settings are copied into the site directory, so
    // their content identifies them, not their path.
    $site_yamls = array_map(fn (string $filename): string => hash_file('xxh128', $filename), $this->serviceYamls['site']);
    $site_providers = array_map(fn (string|object $provider): string => match (TRUE) {
      $provider === $this->sharedContainerTest => $this->sharedContainerTestKey,
      is_object($provider) => $provider::class,
      default => $provider,
    }, $this->serviceProviderClasses['site']);

    // The parent identifier is not used, because it contains the paths of the
    // service files from settings.
    $parts = [
      $this->environment,
      class_exists(DrupalInstalled::class) ? DrupalInstalled::VERSIONS_HASH : \Drupal::VERSION,
      Settings::get('deployment_identifier'),
      PHP_OS,
      // The theme list and the install profile come from configuration, so
      // they are always empty when a shared container is compiled.
      $this->getExtensionFileNames($this->moduleList, [$this, 'moduleData']),
      Language::$defaultValues,
      $site_yamls,
      $site_providers,
      // \Drupal\Core\CoreServiceProvider only registers the private stream
      // wrapper when this is set.
      (bool) Settings::get('file_private_path'),
      Settings::get('container_base_class'),
    ];
    return 'shared:' . hash('xxh128', serialize($parts));
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeContainer() {
    $container = parent::initializeContainer();
    if ($this->sharedContainerTest && !$this->sharedContainerBooted) {
      // Only share the container that the kernel boots with. Containers that
      // are rebuilt later are stored in the same way as without sharing.
      $this->sharedContainerBooted = TRUE;
      $this->allowDumping = $this->allowDumpingAfterBoot;
    }
    return $container;
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
