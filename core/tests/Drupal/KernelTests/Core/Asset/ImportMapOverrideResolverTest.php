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
 * Tests the import map of a theme that overrides a library's importmap.
 *
 * The importmap_test_theme theme overrides the 'importmap' library of the
 * importmap_test module in libraries-override, replacing the source of the
 * 'importmap-test-foo' module specifier with a theme file, and adding
 * integrity metadata for that file.
 */
#[Group('Asset')]
#[RunTestsInSeparateProcesses]
class ImportMapOverrideResolverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['importmap_test', 'importmap_test_conflict'];

  /**
   * The asset resolver service.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * A spy logger, collecting all logged messages.
   *
   * @var \Psr\Log\AbstractLogger&\Drupal\KernelTests\Core\Asset\ImportMapOverrideTestLogger
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Install the test theme and make it the active theme, so that its
    // libraries-override is applied to the library definitions. Installing
    // the theme rebuilds the container, so the services are fetched after.
    $this->container->get('theme_installer')->install(['importmap_test_theme']);
    $this->container->get('theme.manager')
      ->setActiveTheme($this->container->get('theme.initialization')->getActiveThemeByName('importmap_test_theme'));
    // Reset the library discovery, so that it is rebuilt for the new active
    // theme, with its libraries-override applied.
    $this->container->get('library.discovery')->clear();
    // Make sure that the import map is not served from a cache item created
    // by a previous test, since the logged conflicts only happen on a cache
    // miss.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['library_info']);
    $this->assetResolver = $this->container->get('asset.resolver');
    $this->logger = new ImportMapOverrideTestLogger();
    $this->container->get(LoggerChannelFactoryInterface::class)->addLogger($this->logger);
  }

  /**
   * Tests that the theme override is applied to the aggregated import map.
   *
   * The importmap_test module comes before the importmap_test_conflict
   * module in the module list, so its 'importmap-test-foo' entry wins the
   * conflict between the two modules. The theme override replaces that
   * entry with the theme file, and the integrity metadata of the theme file
   * is included in the map.
   */
  public function testImportMapOverride(): void {
    $import_map = $this->assetResolver->getImportMap();
    $path = $this->container->get('extension.list.module')->getPath('importmap_test');
    $conflict_path = $this->container->get('extension.list.module')->getPath('importmap_test_conflict');
    $theme_path = $this->container->get('extension.list.theme')->getPath('importmap_test_theme');
    $this->assertSame([
      'imports' => [
        'importmap-test-foo' => [
          'data' => "$theme_path/js/theme-foo.js",
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
        "$theme_path/js/theme-foo.js" => [
          'data' => "$theme_path/js/theme-foo.js",
          'external' => FALSE,
          'version' => '1.0',
          'integrity' => 'sha384-theme',
        ],
      ],
    ], $import_map);
  }

  /**
   * Tests that a conflict with another extension is still logged.
   *
   * The theme override replaces the 'importmap-test-foo' entry of the
   * importmap_test module, but other libraries still define the same
   * specifier with a different value. Each conflicting definition is logged
   * as an error, and the entry of the module that comes first in the module
   * list, with the theme override applied, is used.
   */
  public function testImportMapOverrideConflictIsLogged(): void {
    $import_map = $this->assetResolver->getImportMap();
    $theme_path = $this->container->get('extension.list.theme')->getPath('importmap_test_theme');
    $this->assertSame("$theme_path/js/theme-foo.js", $import_map['imports']['importmap-test-foo']['data']);
    $conflict_messages = array_filter($this->logger->messages, static function (array $message) {
      return str_contains($message[1], 'Import map conflict');
    });
    // Two conflicts: the 'duplicate' library of the importmap_test module,
    // and the 'conflict' library of the importmap_test_conflict module.
    $this->assertCount(2, $conflict_messages);
    $messages = array_map(static function (array $message) {
      return $message[1];
    }, array_values($conflict_messages));
    $this->assertCount(1, array_filter($messages, static function (string $message) {
      return str_contains($message, 'importmap_test/duplicate');
    }), 'The conflict with the duplicate library is logged.');
    $this->assertCount(1, array_filter($messages, static function (string $message) {
      return str_contains($message, 'importmap_test_conflict/conflict');
    }), 'The conflict with the conflict module is logged.');
    foreach ($messages as $message) {
      $this->assertStringContainsString("the module specifier 'importmap-test-foo' in the 'imports' section is defined by both", $message);
      $this->assertStringContainsString('importmap_test/importmap', $message);
    }
    $this->assertTrue(in_array(array_values($conflict_messages)[0][0], ['error', 3], TRUE), 'The conflict is logged as an error.');
  }

  /**
   * Tests that the module preloads of attached libraries use the override.
   */
  public function testGetModulePreloadsOverride(): void {
    $assets = (new AttachedAssets())->setLibraries(['importmap_test/consumer']);
    $path = $this->container->get('extension.list.module')->getPath('importmap_test');
    $theme_path = $this->container->get('extension.list.theme')->getPath('importmap_test_theme');
    $preloads = $this->assetResolver->getModulePreloads($assets);
    $this->assertSame([
      [
        'data' => "$theme_path/js/theme-foo.js",
        'external' => FALSE,
        'version' => '1.0',
      ],
      [
        'data' => 'https://example.com/importmap-test-external.mjs',
        'external' => TRUE,
        'version' => '1.0',
      ],
      [
        'data' => "$path/js/scope/foo.js",
        'external' => FALSE,
        'version' => '1.0',
      ],
    ], $preloads);
  }

}

/**
 * A logger that collects the messages it receives.
 */
class ImportMapOverrideTestLogger extends AbstractLogger {

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
