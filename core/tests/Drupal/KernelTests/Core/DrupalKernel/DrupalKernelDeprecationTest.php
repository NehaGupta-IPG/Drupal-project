<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DrupalKernel;

use Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DrupalKernel;
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
    $this->expectUserDeprecationMessage('Drupal\Core\DrupalKernel::getCachedContainerDefinition() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Container loading is now handled by ContainerStorageInterface::load(). See https://www.drupal.org/node/3585642');
    $this->container->get('kernel')->getCachedContainerDefinition();
  }

  /**
   * Tests that getContainerCacheKey() triggers a deprecation.
   */
  #[IgnoreDeprecations]
  public function testGetContainerCacheKeyDeprecation(): void {
    $this->expectUserDeprecationMessage('Drupal\Core\DrupalKernel::getContainerCacheKey() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use getContainerCacheKeySuffix() instead. The cache key prefix is now managed by ContainerStorageInterface implementations. See https://www.drupal.org/node/3585642');
    $kernel = $this->container->get('kernel');
    $method = new \ReflectionMethod($kernel, 'getContainerCacheKey');
    $key = $method->invoke($kernel);
    $this->assertStringStartsWith('service_container:', $key);
  }

  /**
   * Tests that cacheDrupalContainer() triggers a deprecation.
   */
  #[IgnoreDeprecations]
  public function testCacheDrupalContainerDeprecation(): void {
    $this->expectUserDeprecationMessage('Drupal\Core\DrupalKernel::cacheDrupalContainer() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Container dumping is now handled by ContainerStorageInterface::dump(). See https://www.drupal.org/node/3585642');
    $kernel = $this->container->get('kernel');
    $method = new \ReflectionMethod($kernel, 'cacheDrupalContainer');
    $method->invoke($kernel, []);
  }

  /**
   * Tests that a custom $phpArrayDumperClass triggers a deprecation on boot.
   */
  #[IgnoreDeprecations]
  public function testPhpArrayDumperClassDeprecation(): void {
    $this->expectUserDeprecationMessage('Drupal\Core\DrupalKernel::$phpArrayDumperClass is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use the bootstrap_container_definition setting to set the class. See https://www.drupal.org/node/3585642');

    // Create a custom dumper class that differs from the default.
    $customDumperClass = get_class(new class (new ContainerBuilder()) extends OptimizedPhpArrayDumper {});

    $kernel = $this->container->get('kernel');
    $property = new \ReflectionProperty($kernel, 'phpArrayDumperClass');
    $property->setValue($kernel, $customDumperClass);

    $kernel->shutdown();
    $kernel->boot();

    // Confirm the custom class was actually wired into the storage service,
    // not just that the deprecation notice fired.
    $bootstrapContainer = (new \ReflectionProperty($kernel, 'bootstrapContainer'))->getValue($kernel);
    $storage = $bootstrapContainer->get('container_storage.cache');
    $dumperClass = (new \ReflectionProperty($storage, 'dumperClass'))->getValue($storage);
    $this->assertSame($customDumperClass, $dumperClass);
  }

}
