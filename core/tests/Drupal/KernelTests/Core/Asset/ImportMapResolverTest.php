<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Asset;

use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\AbstractLogger;

/**
 * Tests the import map functionality of the asset resolver.
 */
#[Group('Asset')]
#[RunTestsInSeparateProcesses]
class ImportMapResolverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['importmap_test', 'importmap_test_alter', 'importmap_test_conflict'];

  /**
   * The asset resolver service.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * A spy logger, collecting all logged messages.
   *
   * @var \Psr\Log\AbstractLogger&\Drupal\KernelTests\Core\Asset\ImportMapTestLogger
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->assetResolver = $this->container->get('asset.resolver');
    $this->logger = new ImportMapTestLogger();
    $this->container->get(LoggerChannelFactoryInterface::class)->addLogger($this->logger);
    // Make sure that the import map is not served from a cache item created
    // by a previous test, since the logged conflicts only happen on a cache
    // miss.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['library_info']);
  }

  /**
   * Tests that the import map contains all entries of all known libraries.
   */
  public function testGetImportMap(): void {
    $import_map = $this->assetResolver->getImportMap();
    $path = $this->container->get('extension.list.module')->getPath('importmap_test');
    $conflict_path = $this->container->get('extension.list.module')->getPath('importmap_test_conflict');
    // The 'importmap-test-foo' entry is defined by both importmap_test and
    // importmap_test_conflict with different values. The entry of the module
    // that comes first in the module list wins.
    $modules = array_keys($this->container->get('module_handler')->getModuleList());
    $winner = array_search('importmap_test', $modules) < array_search('importmap_test_conflict', $modules) ? 'importmap_test' : 'importmap_test_conflict';
    $winner_data = $winner === 'importmap_test' ? "$path/js/foo.js" : "$conflict_path/js/conflict-foo.js";
    $this->assertSame([
      'imports' => [
        'importmap-test-foo' => [
          'data' => $winner_data,
          'external' => FALSE,
          'version' => '1.0',
        ],
        'importmap-test-prefix/' => [
          'data' => "$path/js/prefix/",
          'external' => FALSE,
          'version' => '1.0',
        ],
        'importmap-test-external' => [
          'data' => 'https://example.com/importmap-test-external.mjs',
          'external' => TRUE,
          'version' => '1.0',
        ],
        'importmap-test-altered' => [
          'data' => "$path/js/altered.js",
          'external' => FALSE,
          'version' => '1.0',
        ],
        'importmap-test-duplicate' => [
          'data' => "$conflict_path/js/duplicate.js",
          'external' => FALSE,
          'version' => '1.0',
        ],
      ],
      'scopes' => [
        "$path/js/scope/" => [
          'importmap-test-foo' => [
            'data' => "$path/js/scope/foo.js",
            'external' => FALSE,
            'version' => '1.0',
          ],
        ],
      ],
      'integrity' => [
        "$path/js/foo.js" => [
          'data' => "$path/js/foo.js",
          'external' => FALSE,
          'version' => '1.0',
          'integrity' => 'sha384-oXiGjHO+NOxR1XH8somfajtqMWa5PTleNh5VGncEbQQTrZZboU8xIMhoDzGBbroU',
        ],
      ],
    ], $import_map);
    // An error is logged for the conflicting entry, mentioning both
    // providers.
    $conflict_messages = array_filter($this->logger->messages, static function (array $message) {
      return str_contains($message[1], 'Import map conflict');
    });
    $this->assertCount(1, $conflict_messages);
    $conflict_message = array_values($conflict_messages)[0];
    $this->assertTrue(in_array($conflict_message[0], ['error', 3], TRUE), 'The conflict is logged as an error.');
    $this->assertStringContainsString("the module specifier 'importmap-test-foo' in the 'imports' section is defined by both", $conflict_message[1]);
    $this->assertStringContainsString('importmap_test/importmap', $conflict_message[1]);
    $this->assertStringContainsString('importmap_test_conflict/conflict', $conflict_message[1]);
    // The message points to hook_importmap_alter() to resolve the conflict.
    $this->assertStringContainsString('hook_importmap_alter', $conflict_message[1]);
    // The duplicate entry of the same module, with a different library
    // version, does not conflict.
    $this->assertStringNotContainsString('importmap_test/duplicate', $conflict_message[1]);
  }

  /**
   * Tests that the import map can be altered in hook_importmap_alter().
   *
   * The importmap_test_alter module changes the 'importmap-test-foo' entry,
   * without resolving its conflict, so the conflict is still logged.
   */
  public function testGetImportMapAlteredByImportMapAlter(): void {
    $this->container->get('state')->set('importmap_test_alter.mode', 'modify');
    $import_map = $this->assetResolver->getImportMap();
    $path = $this->container->get('extension.list.module')->getPath('importmap_test');
    $this->assertSame([
      'data' => "$path/js/altered.js",
      'external' => FALSE,
      'version' => '1.0',
    ], $import_map['imports']['importmap-test-foo']);
    // The alteration does not resolve the conflict, so it is still logged.
    $conflict_messages = array_filter($this->logger->messages, static function (array $message) {
      return str_contains($message[1], 'Import map conflict');
    });
    $this->assertCount(1, $conflict_messages);
  }

  /**
   * Tests that a conflict resolved in hook_importmap_alter() is not logged.
   *
   * The importmap_test_alter module resolves the 'importmap-test-foo'
   * conflict by using the definition of the importmap_test_conflict module,
   * and unsetting the conflict.
   */
  public function testGetImportMapConflictResolvedByImportMapAlter(): void {
    $this->container->get('state')->set('importmap_test_alter.mode', 'resolve');
    $import_map = $this->assetResolver->getImportMap();
    $conflict_path = $this->container->get('extension.list.module')->getPath('importmap_test_conflict');
    $this->assertSame([
      'data' => "$conflict_path/js/conflict-foo.js",
      'external' => FALSE,
      'version' => '1.0',
    ], $import_map['imports']['importmap-test-foo']);
    // The conflict is resolved in hook_importmap_alter(), so it is not
    // logged.
    $conflict_messages = array_filter($this->logger->messages, static function (array $message) {
      return str_contains($message[1], 'Import map conflict');
    });
    $this->assertCount(0, $conflict_messages);
  }

  /**
   * Tests that the import map altered in hook_importmap_alter() is cached.
   */
  public function testGetImportMapAlteredIsCached(): void {
    $this->container->get('state')->set('importmap_test_alter.mode', 'modify');
    $import_map = $this->assetResolver->getImportMap();
    $this->assertSame($import_map, $this->assetResolver->getImportMap());
    // The conflict is only logged once, on the cache miss.
    $conflict_messages = array_filter($this->logger->messages, static function (array $message) {
      return str_contains($message[1], 'Import map conflict');
    });
    $this->assertCount(1, $conflict_messages);
  }

  /**
   * Tests an import map entry added by hook_library_info_alter().
   *
   * The entry is validated, and its path is resolved by the library parser.
   */
  public function testGetImportMapAlteredByLibraryInfoAlter(): void {
    $import_map = $this->assetResolver->getImportMap();
    $path = $this->container->get('extension.list.module')->getPath('importmap_test');
    $this->assertSame([
      'data' => "$path/js/altered.js",
      'external' => FALSE,
      'version' => '1.0',
    ], $import_map['imports']['importmap-test-altered']);
  }

  /**
   * Tests that the import map is cached.
   */
  public function testGetImportMapIsCached(): void {
    $import_map = $this->assetResolver->getImportMap();
    $this->assertSame($import_map, $this->assetResolver->getImportMap());
    // The conflict is only logged once, on the cache miss.
    $conflict_messages = array_filter($this->logger->messages, static function (array $message) {
      return str_contains($message[1], 'Import map conflict');
    });
    $this->assertCount(1, $conflict_messages);
  }

  /**
   * Tests the module preloads for attached libraries.
   *
   * The preloads include the 'importmap-test-altered' entry, added to the
   * importmap library by the importmap_test_alter module in
   * hook_library_info_alter().
   */
  public function testGetModulePreloads(): void {
    $assets = (new AttachedAssets())->setLibraries(['importmap_test/consumer']);
    $path = $this->container->get('extension.list.module')->getPath('importmap_test');
    $preloads = $this->assetResolver->getModulePreloads($assets);
    $this->assertSame([
      [
        'data' => "$path/js/foo.js",
        'external' => FALSE,
        'version' => '1.0',
      ],
      [
        'data' => 'https://example.com/importmap-test-external.mjs',
        'external' => TRUE,
        'version' => '1.0',
      ],
      [
        'data' => "$path/js/altered.js",
        'external' => FALSE,
        'version' => '1.0',
      ],
      [
        'data' => "$path/js/scope/foo.js",
        'external' => FALSE,
        'version' => '1.0',
      ],
    ], $preloads);
  }

  /**
   * Tests that the preloads of dependency libraries are included.
   */
  public function testGetModulePreloadsWithDependencies(): void {
    $assets = (new AttachedAssets())->setLibraries(['importmap_test/scoped-consumer']);
    $path = $this->container->get('extension.list.module')->getPath('importmap_test');
    $preloads = $this->assetResolver->getModulePreloads($assets);
    $this->assertSame([
      [
        'data' => "$path/js/foo.js",
        'external' => FALSE,
        'version' => '1.0',
      ],
      [
        'data' => 'https://example.com/importmap-test-external.mjs',
        'external' => TRUE,
        'version' => '1.0',
      ],
      [
        'data' => "$path/js/altered.js",
        'external' => FALSE,
        'version' => '1.0',
      ],
      [
        'data' => "$path/js/scope/foo.js",
        'external' => FALSE,
        'version' => '1.0',
      ],
    ], $preloads);
  }

  /**
   * Tests that no preloads are returned when no libraries are attached.
   */
  public function testGetModulePreloadsWithoutLibraries(): void {
    $assets = new AttachedAssets();
    $this->assertSame([], $this->assetResolver->getModulePreloads($assets));
  }

}

/**
 * A logger that collects the messages it receives.
 */
class ImportMapTestLogger extends AbstractLogger {

  /**
   * The collected messages, as [level, message] arrays.
   *
   * @var array
   */
  public $messages = [];

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $this->messages[] = [$level, $message];
  }

}
