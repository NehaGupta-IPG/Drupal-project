<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\StreamWrapper;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\KernelTests\KernelTestBase;
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
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->streamWrapperManager = \Drupal::service('stream_wrapper_manager');
  }

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

}
