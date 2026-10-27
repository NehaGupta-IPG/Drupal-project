<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\StreamWrapper;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\stream_wrapper_service_test\StreamWrapper\ServiceDependencyStreamWrapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\Core\StreamWrapper\StreamWrapperManager.
 */
#[CoversClass(StreamWrapperManager::class)]
#[Group('File')]
#[RunTestsInSeparateProcesses]
class StreamWrapperManagerTest extends KernelTestBase {

  /**
   * Tests uri scheme.
   *
   * @legacy-covers ::getScheme
   */
  #[DataProvider('providerTestUriScheme')]
  public function testUriScheme($uri, $expected): void {
    $this->assertSame($expected, StreamWrapperManager::getScheme($uri));
  }

  /**
   * Tests getNames().
   *
   * @legacy-covers ::getNames
   */
  public function testGetNames(): void {
    $names = $this->streamWrapperManager->getNames();
    $names = array_map(fn ($value) => (string) $value, $names);
    $this->assertSame([
      'assets' => 'Optimized assets files',
      'public' => 'Public files',
      'temporary' => 'Temporary files',
      'module' => 'Module files',
      'theme' => 'Theme files',
    ], $names);

    $names = $this->streamWrapperManager->getNames(StreamWrapperInterface::VISIBLE);
    $names = array_map(fn ($value) => (string) $value, $names);
    $this->assertSame([
      'public' => 'Public files',
    ], $names);
  }

  /**
   * Tests getDescriptions().
   *
   * @legacy-covers ::getDescriptions
   */
  public function testGetDescriptions(): void {
    $descriptions = $this->streamWrapperManager->getDescriptions();
    $descriptions = array_map(fn ($value) => (string) $value, $descriptions);
    $this->assertSame([
      'assets' => 'Public local optimized assets files served by the webserver.',
      'public' => 'Public local files served by the webserver.',
      'temporary' => 'Temporary local files for upload and previews.',
      'module' => 'Local files stored under a module\'s directory.',
      'theme' => 'Local files stored under a theme\'s directory.',
    ], $descriptions);

    $descriptions = $this->streamWrapperManager->getDescriptions(StreamWrapperInterface::VISIBLE);
    $descriptions = array_map(fn ($value) => (string) $value, $descriptions);
    $this->assertSame([
      'public' => 'Public local files served by the webserver.',
    ], $descriptions);
  }

  /**
   * Data provider.
   */
  public static function providerTestUriScheme(): array {
    $data = [];
    $data[] = [
      'public://filename',
      'public',
    ];
    $data[] = [
      'public://extra://',
      'public',
    ];
    $data[] = [
      'invalid',
      FALSE,
    ];
    return $data;
  }

  /**
   * Tests installing a module providing a stream wrapper using services.
   */
  public function testModuleInstallRegistration(): void {
    $this->container->get('stream_wrapper_manager')->register();
    $this->container->get('module_installer')->install(['stream_wrapper_service_test']);

    $manager = \Drupal::service('stream_wrapper_manager');
    $this->assertSame(ServiceDependencyStreamWrapper::class, $manager->getClass('test'));
    $wrapper = $manager->getViaScheme('test');
    $this->assertInstanceOf(ServiceDependencyStreamWrapper::class, $wrapper);
    $this->assertSame($manager, $wrapper->streamWrapperManager);
  }

}
