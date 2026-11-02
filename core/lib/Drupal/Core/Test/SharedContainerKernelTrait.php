<?php

declare(strict_types=1);

namespace Drupal\Core\Test;

use Drupal\Core\Language\Language;
use Drupal\Core\Site\Settings;
use Drupal\DrupalInstalled;

/**
 * Builds identifiers of containers that tests share.
 *
 * Tests compile the same containers many times, because each test builds its
 * own site. When the module list is known, a kernel used in tests can build an
 * identifier from everything that the compiled container depends on, and load
 * a container that another test stored under the same identifier.
 *
 * @see \Drupal\Core\Test\TestContainerStorage
 *
 * @internal
 */
trait SharedContainerKernelTrait {

  /**
   * Returns the identifier of a container that is shared with other tests.
   *
   * The identifier covers what the compiled container depends on. The site
   * path, which differs for every test, is not part of it: the container
   * storage sets it when it loads the container.
   *
   * @return string
   *   The container identifier.
   */
  protected function getSharedContainerIdentifier(): string {
    $this->discoverServiceProviders();

    // Service files from settings are copied into the site directory, so
    // their content identifies them, not their path.
    $site_yamls = array_map(fn (string $filename): string => hash_file('xxh128', $filename), $this->serviceYamls['site']);
    $site_providers = array_map($this->getServiceProviderKey(...), $this->serviceProviderClasses['site']);

    // The parent identifier is not used, because it contains the paths of the
    // service files from settings.
    $parts = [
      static::class,
      $this->environment,
      class_exists(DrupalInstalled::class) ? DrupalInstalled::VERSIONS_HASH : \Drupal::VERSION,
      Settings::get('deployment_identifier'),
      PHP_OS,
      $this->getExtensionFileNames($this->moduleList, [$this, 'moduleData']),
      Language::$defaultValues,
      $site_yamls,
      $site_providers,
      // \Drupal\Core\CoreServiceProvider only registers the private stream
      // wrapper when this is set.
      (bool) Settings::get('file_private_path'),
      Settings::get('container_base_class'),
      $this->getContainerConfiguration(),
    ];
    return 'shared:' . hash('xxh128', serialize($parts));
  }

  /**
   * Returns the configuration and state that compiling the container reads.
   *
   * @return array
   *   The configuration and state.
   */
  protected function getContainerConfiguration(): array {
    $config_storage = $this->getConfigStorage();
    $system_site = $config_storage->read('system.site') ?: [];
    return [
      $this->getExtensionFileNames($this->themeList ?? [], [$this, 'themeExtensions']),
      $this->getInstallProfile() ?: NULL,
      // The default language.
      // @see \Drupal\Core\DrupalKernel::compileContainer()
      // @see \Drupal\language\LanguageServiceProvider
      $system_site['langcode'] ?? NULL,
      $system_site['default_langcode'] ?? NULL,
      $config_storage->listAll('language.entity.'),
      // @see \Drupal\locale\LocaleServiceProvider
      $config_storage->read('locale.settings'),
      // @see \Drupal\Core\DependencyInjection\Compiler\DevelopmentSettingsPass
      isset($this->container) ? $this->container->get('keyvalue')->get('development_settings')->getAll() : [],
    ];
  }

  /**
   * Returns the container storage if tests share their containers.
   *
   * @return \Drupal\Core\Test\TestContainerStorage|null
   *   The container storage, or NULL if containers are not shared.
   */
  protected function getSharedContainerStorage(): ?TestContainerStorage {
    $storage = isset($this->bootstrapContainer) ? $this->bootstrapContainer->get('container_storage') : NULL;
    return $storage instanceof TestContainerStorage ? $storage : NULL;
  }

  /**
   * Returns a key for a site-specific service provider.
   *
   * @param string|object $provider
   *   The service provider class name, or service provider object.
   *
   * @return string
   *   The key.
   */
  protected function getServiceProviderKey(string|object $provider): string {
    return match (TRUE) {
      $provider instanceof SharedContainerTestInterface => $provider->getContainerKey(),
      is_object($provider) => $provider::class,
      default => $provider,
    };
  }

}
