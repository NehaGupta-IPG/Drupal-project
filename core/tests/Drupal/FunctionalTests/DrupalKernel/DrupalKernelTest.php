<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\DrupalKernel;

use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests DrupalKernel caching and autoloading.
 *
 * For some test cases, a fresh PHP process is required to ensure PSR4
 * autoloading is not poisoned by other systems. BrowserTests are used since
 * a new PHP process is guaranteed.
 *
 * @group DrupalKernel
 * @coversDefaultClass \Drupal\Core\DrupalKernel
 */
final class DrupalKernelTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * {@inheritdoc}
   *
   * Default theme is not relevant to this test.
   */
  protected $defaultTheme = 'stark';

  /**
   * Test container with parameter references to modules.
   *
   * Tests references to objects or enums defined by modules are unserialized
   * and rehydrated successfully, since the classloader is populated before the
   * cached container definition is unserialized.
   *
   * @covers \Drupal\Core\DrupalKernel::initializeContainer
   * @see \Drupal\Core\DrupalKernel::getCachedContainerDiscovery
   * @see \Drupal\Core\DrupalKernel::cacheDrupalContainer
   * @see \Drupal\Core\DependencyInjection\ContainerDiscovery
   * @see \Drupal\service_provider_test\ServiceProviderTestServiceProvider::alter
   */
  public function testCachedReferencesToExtensions(): void {
    $kernel = \Drupal::service('kernel');

    $bootstrapContainer = (new \ReflectionClass($kernel::class))->getProperty('bootstrapContainer')->getValue($kernel);
    $this->assertInstanceOf(ContainerInterface::class, $bootstrapContainer);

    $containerCache = $bootstrapContainer->get('cache.container');

    // Ensure the `bootstrap` bin is using database backend, since assertions
    // rely on querying the database directly.
    $this->assertInstanceOf(DatabaseBackend::class, $containerCache);
    $tableName = (new \ReflectionClass($containerCache::class))->getProperty('bin')->getValue($containerCache);

    \Drupal::service(ModuleInstallerInterface::class)->install(['autoloader_test']);

    // Ensure the cache is populated.
    // Count is used since the cache ID (via DrupalKernel::getContainerCacheKey)
    // of the web process is not reliably knowable.
    // Contains an entry for both discovery and container definition.
    $this->assertEquals(2, \Drupal::database()->select($tableName)->countQuery()->execute()->fetchField());

    // Get the result of assertions from a controller, since we can't assert
    // if parameters are set from this process.

    $this->drupalGet(Url::fromRoute('autoloader_test.parameters'));

    // Without PSR-4 being populated after discovery unserialization, the
    // response will contain unserialize() exceptions.
    $this->assertSession()->responseContains('Enum:Drupal\autoloader_test\AutoloaderTestEnum');
    $this->assertSession()->responseContains('Class:Drupal\autoloader_test\AutoloaderTestClass');
  }
}
