<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DrupalKernel;

use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests DrupalKernel deprecation notices.
 */
#[CoversClass(DrupalKernel::class)]
#[Group('DrupalKernel')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class DrupalKernelDeprecationTest extends KernelTestBase {

  /**
   * Tests that getCachedContainerDefinition() triggers a deprecation.
   */
  #[IgnoreDeprecations]
  public function testGetCachedContainerDefinitionDeprecation(): void {
    $this->expectUserDeprecationMessage('Drupal\Core\DrupalKernel::getCachedContainerDefinition() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Container loading is now handled by ContainerStorageInterface::load(). See https://www.drupal.org/node/3585642');
    $this->container->get('kernel')->getCachedContainerDefinition();
  }

  /**
   * Tests that getContainerCacheKey() triggers a deprecation.
   */
  #[IgnoreDeprecations]
  public function testGetContainerCacheKeyDeprecation(): void {
    $this->expectUserDeprecationMessage('Drupal\Core\DrupalKernel::getContainerCacheKey() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use getContainerCacheKeySuffix() instead. The cache key prefix is now managed by ContainerStorageInterface implementations. See https://www.drupal.org/node/3585642');
    $kernel = $this->container->get('kernel');
    $method = new \ReflectionMethod($kernel, 'getContainerCacheKey');
    $method->invoke($kernel);
  }

  /**
   * Tests that cacheDrupalContainer() triggers a deprecation.
   */
  #[IgnoreDeprecations]
  public function testCacheDrupalContainerDeprecation(): void {
    $this->expectUserDeprecationMessage('Drupal\Core\DrupalKernel::cacheDrupalContainer() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Container dumping is now handled by ContainerStorageInterface::dump(). See https://www.drupal.org/node/3585642');
    $kernel = $this->container->get('kernel');
    $method = new \ReflectionMethod($kernel, 'cacheDrupalContainer');
    $method->invoke($kernel, []);
  }

  /**
   * Tests deprecation when bootstrap_container_definition is missing services.
   */
  #[IgnoreDeprecations]
  public function testBootstrapContainerDefinitionMissingServicesDeprecation(): void {
    $this->expectUserDeprecationMessage('The bootstrap_container_definition setting is missing the following services: kernel, settings, container_storage, container_storage.cache, container_storage.php_dumper, php_storage.service_container. These services will be added automatically but this is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Update your bootstrap_container_definition in settings.php to include these services. See https://www.drupal.org/node/3585642');

    // Set a custom bootstrap_container_definition that only has the legacy
    // services (missing all new container storage services).
    $settings = Settings::getAll();
    $settings['bootstrap_container_definition'] = [
      'parameters' => [],
      'services' => [
        'database' => [
          'class' => 'Drupal\Core\Database\Connection',
          'factory' => 'Drupal\Core\Database\Database::getConnection',
          'arguments' => ['default'],
        ],
        'cache.container' => [
          'class' => '\Drupal\Core\Cache\DatabaseBackend',
          'arguments' => [
            '@database',
            '@cache_tags_provider.container',
            'container',
            '@serialization.phpserialize',
            '@datetime.time',
            DatabaseBackend::MAXIMUM_NONE,
          ],
        ],
        'cache_tags_provider.container' => [
          'class' => 'Drupal\Core\Cache\DatabaseCacheTagsChecksum',
          'arguments' => ['@database'],
        ],
        'serialization.phpserialize' => [
          'class' => '\Drupal\Component\Serialization\PhpSerialize',
        ],
        'request_stack' => [
          'class' => 'Symfony\Component\HttpFoundation\RequestStack',
        ],
        'datetime.time' => [
          'class' => 'Drupal\Component\Datetime\Time',
          'arguments' => ['@request_stack'],
        ],
      ],
    ];
    new Settings($settings);

    // Shutdown the existing kernel and re-boot it. This triggers boot() which
    // reads the bootstrap_container_definition from settings and detects the
    // missing services.
    $kernel = $this->container->get('kernel');
    $kernel->shutdown();
    $kernel->boot();
  }

}
