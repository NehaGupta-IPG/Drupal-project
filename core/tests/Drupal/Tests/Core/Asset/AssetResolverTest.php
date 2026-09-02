<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Asset;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Asset\AssetResolver;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Asset\JsCollectionGrouper;
use Drupal\Core\Asset\LibraryDependencyResolver;
use Drupal\Core\Asset\LibraryDiscoveryCollector;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;

/**
 * Tests Drupal\Core\Asset\AssetResolver.
 */
#[CoversClass(AssetResolver::class)]
#[Group('Asset')]
class AssetResolverTest extends UnitTestCase {

  /**
   * The tested asset resolver service.
   *
   * @var \Drupal\Core\Asset\AssetResolver
   */
  protected $assetResolver;

  /**
   * The library discovery service.
   */
  protected LibraryDiscoveryInterface&Stub $libraryDiscovery;

  /**
   * The library dependency resolver.
   *
   * @var \Drupal\Core\Asset\LibraryDependencyResolverInterface
   */
  protected $libraryDependencyResolver;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface&Stub $moduleHandler;

  /**
   * The theme manager.
   */
  protected ThemeManagerInterface&Stub $themeManager;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface&Stub $languageManager;

  /**
   * The cache backend to use.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The theme handler.
   */
  protected ThemeHandlerInterface&Stub $themeHandler;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected $logger;

  /**
   * A English language object.
   */
  protected LanguageInterface&Stub $english;

  /**
   * A Japanese language object.
   */
  protected LanguageInterface&Stub $japanese;
  /**
   * An array of library definitions.
   */
  protected array $libraries = [];

  /**
   * The library definitions, keyed by extension.
   *
   * @var array<string, array<string, array>>
   */
  protected array $extensionLibraries = [];

  /**
   * The enabled module names.
   *
   * @var string[]
   */
  protected array $modules = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->libraryDiscovery = $this->createStub(LibraryDiscoveryCollector::class);
    $this->libraryDiscovery
      ->method('getLibraryByName')
      ->willReturnCallback(function (string $extension, string $name) {
        return $this->libraries[$extension . '/' . $name];
      });
    $this->libraries = [
      'core/drupal' => [
        'version' => '1.0.0',
        'aggregate_target' => ['css' => FALSE, 'js' => FALSE],
        'css' => [],
        'js' =>
          [
            'core/misc/drupal.js' => ['data' => 'core/misc/drupal.js', 'preprocess' => TRUE],
          ],
        'license' => '',
      ],
      'core/jquery' => [
        'version' => '1.0.0',
        'aggregate_target' => ['css' => FALSE, 'js' => FALSE],
        'css' => [],
        'js' =>
          [
            'core/misc/jquery.js' => ['data' => 'core/misc/jquery.js', 'minified' => TRUE],
          ],
        'license' => '',
      ],
      'llama/css' => [
        'version' => '1.0.0',
        'aggregate_target' => ['css' => FALSE, 'js' => FALSE],
        'css' =>
          [
            'core/misc/llama.css' => ['data' => 'core/misc/llama.css'],
          ],
        'js' => [],
        'fonts' => [],
        'license' => '',
      ],
      'piggy/css' => [
        'version' => '1.0.0',
        'aggregate_target' => ['css' => FALSE, 'js' => FALSE],
        'css' =>
          [
            'core/misc/piggy.css' => ['data' => 'core/misc/piggy.css'],
          ],
        'js' => [],
        'fonts' => [
          'fonts/font.woff2' => [
            'data' => 'fonts/font.woff2',
            'preload' => TRUE,
          ],
        ],
        'license' => '',
      ],
      'core/ckeditor5' => [
        'remote' => 'https://github.com/ckeditor/ckeditor5',
        'version' => '1.0.0',
        'license' => '',
        'aggregate_target' => ['css' => FALSE, 'js' => FALSE],
        'js' => [
          'assets/vendor/ckeditor5/ckeditor5.umd.js' => [
            'data' => 'assets/vendor/ckeditor5/ckeditor5.umd.js',
            'preprocess' => FALSE,
            'minified' => TRUE,
          ],
        ],
      ],
      'piggy/ckeditor' => [
        'version' => '1.0.0',
        'css' =>
          [
            'core/misc/ckeditor.css' => ['data' => 'core/misc/ckeditor.css'],
          ],
        'js' => [],
        'license' => '',
        'dependencies' => [
          'core/ckeditor5',
        ],
      ],
      'llama/llama-library' => [
        'version' => '2.0',
        'css' => [],
        'js' => [],
        'importmap' => [
          'imports' => [
            'llama' => ['data' => 'modules/llama/llama.mjs', 'external' => FALSE, 'version' => '2.0'],
            'llama-prefix/' => ['data' => 'modules/llama/', 'external' => FALSE, 'version' => '2.0'],
            'llama-ext' => ['data' => 'https://example.com/llama.mjs', 'external' => TRUE, 'version' => '2.0'],
          ],
          'scopes' => [
            '/modules/llama/v1' => [
              'llama' => ['data' => 'modules/llama/v1/llama.mjs', 'external' => FALSE, 'version' => '1.0'],
            ],
          ],
          'integrity' => [
            'modules/llama/llama.mjs' => [
              'data' => 'modules/llama/llama.mjs',
              'external' => FALSE,
              'version' => '2.0',
              'integrity' => 'sha384-llama',
            ],
          ],
        ],
        'license' => '',
      ],
      'piggy/consumer' => [
        'version' => '1.0',
        'css' => [],
        'js' => [],
        'importmap' => [],
        'license' => '',
        'dependencies' => [
          'llama/llama-library',
        ],
      ],
    ];
    $this->libraryDependencyResolver = new LibraryDependencyResolver($this->libraryDiscovery);
    $this->libraryDiscovery
      ->method('getLibrariesByExtension')
      ->willReturnCallback(function (string $extension) {
        return $this->extensionLibraries[$extension] ?? [];
      });
    $this->moduleHandler = $this->createStub(ModuleHandlerInterface::class);
    $this->moduleHandler
      ->method('getModuleList')
      ->willReturnCallback(fn() => array_combine($this->modules, array_fill(0, count($this->modules), NULL)));
    $active_theme = new Extension($this->root, 'theme', 'core/themes/stark/stark.info.yml');
    $this->themeManager = $this->createStub(ThemeManagerInterface::class);
    $this->themeManager
      ->method('getActiveTheme')
      ->willReturn($active_theme);

    $english = $this->createStub('\Drupal\Core\Language\LanguageInterface');
    $english
      ->method('getId')
      ->willReturn('en');
    $this->english = $english;
    $japanese = $this->createStub('\Drupal\Core\Language\LanguageInterface');
    $japanese
      ->method('getId')
      ->willReturn('jp');
    $this->japanese = $japanese;
    $this->languageManager = $this->createStub(LanguageManagerInterface::class);
    $this->languageManager
      ->method('getCurrentLanguage')
      ->willReturn($english, $english, $japanese, $japanese);
    $this->cache = new TestMemoryBackend(new Time());

    $this->themeHandler = $this->createStub(ThemeHandlerInterface::class);
    $this->themeHandler
      ->method('getTheme')
      ->willReturn(new Extension($this->root, 'theme', 'core/themes/stark/stark.info.yml'));

    $this->logger = $this->createStub(LoggerChannelInterface::class);

    $this->assetResolver = new AssetResolver($this->libraryDiscovery, $this->libraryDependencyResolver, $this->moduleHandler, $this->themeManager, $this->languageManager, $this->cache, $this->themeHandler, $this->logger);
  }

  /**
   * Re-creates the asset resolver with a mock logger.
   *
   * Used by tests that assert on the log messages of the asset resolver.
   */
  protected function createAssetResolverWithLogger(): void {
    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $this->assetResolver = new AssetResolver($this->libraryDiscovery, $this->libraryDependencyResolver, $this->moduleHandler, $this->themeManager, $this->languageManager, $this->cache, $this->themeHandler, $this->logger);
  }

  /**
   * Tests get css assets.
   */
  #[DataProvider('providerAttachedCssAssets')]
  public function testGetCssAssets(AttachedAssetsInterface $assets_a, AttachedAssetsInterface $assets_b, int $expected_css_cache_item_count): void {
    $this->libraryDiscovery
      ->method('getLibraryByName')
      ->willReturnCallback(function (string $extension, string $name) {
        return $this->libraries[$extension . '/' . $name];
      });
    $this->assetResolver->getCssAssets($assets_a, FALSE, $this->english);
    $this->assetResolver->getCssAssets($assets_b, FALSE, $this->english);
    $this->assertCount($expected_css_cache_item_count, $this->cache->getAllCids());
  }

  public static function providerAttachedCssAssets(): array {
    return [
      'one js only library and one css only library' => [
        (new AttachedAssets())->setAlreadyLoadedLibraries([])->setLibraries(['core/drupal']),
        (new AttachedAssets())->setAlreadyLoadedLibraries([])->setLibraries(['llama/css']),
        1,
      ],
      'two different css libraries' => [
        (new AttachedAssets())->setAlreadyLoadedLibraries([])->setLibraries(['core/drupal', 'llama/css']),
        (new AttachedAssets())->setAlreadyLoadedLibraries([])->setLibraries(['piggy/css']),
        2,
      ],
    ];
  }

  /**
   * Tests get js assets.
   */
  #[DataProvider('providerAttachedJsAssets')]
  public function testGetJsAssets(AttachedAssetsInterface $assets_a, AttachedAssetsInterface $assets_b, int $expected_js_cache_item_count, int $expected_multilingual_js_cache_item_count): void {
    $this->libraryDiscovery
      ->method('getLibraryByName')
      ->willReturnCallback(function (string $extension, string $name) {
        return $this->libraries[$extension . '/' . $name];
      });
    $this->assetResolver->getJsAssets($assets_a, FALSE, $this->english);
    $this->assetResolver->getJsAssets($assets_b, FALSE, $this->english);
    $this->assertCount($expected_js_cache_item_count, $this->cache->getAllCids());

    $this->assetResolver->getJsAssets($assets_a, FALSE, $this->japanese);
    $this->assetResolver->getJsAssets($assets_b, FALSE, $this->japanese);
    $this->assertCount($expected_multilingual_js_cache_item_count, $this->cache->getAllCids());
  }

  public static function providerAttachedJsAssets(): array {
    $time = time();
    return [
      'same libraries, different timestamps' => [
        (new AttachedAssets())->setAlreadyLoadedLibraries([])->setLibraries(['core/drupal'])->setSettings(['currentTime' => $time]),
        (new AttachedAssets())->setAlreadyLoadedLibraries([])->setLibraries(['core/drupal'])->setSettings(['currentTime' => $time + 100]),
        1,
        2,
      ],
      'different libraries, same timestamps' => [
        (new AttachedAssets())->setAlreadyLoadedLibraries([])->setLibraries(['core/drupal'])->setSettings(['currentTime' => $time]),
        (new AttachedAssets())->setAlreadyLoadedLibraries([])->setLibraries(['core/drupal', 'core/jquery'])->setSettings(['currentTime' => $time]),
        2,
        4,
      ],
    ];
  }

  /**
   * Tests get font assets.
   */
  #[DataProvider('providerAttachedFontAssets')]
  public function testGetFontAssets(array $libraries, array $expected): void {
    $assets = new AttachedAssets()->setAlreadyLoadedLibraries([])->setLibraries($libraries);
    $fonts = $this->assetResolver->getFontAssets($assets, $this->english);
    $this->assertSame($expected, $fonts);
  }

  public static function providerAttachedFontAssets(): array {
    return [
      [
        ['piggy/css'],
        [
          [
            'data' => 'fonts/font.woff2',
            'preload' => TRUE,
          ],
        ],
      ],
      [
        ['llama/css'],
        [],
      ],
    ];
  }

  /**
   * Test that order of scripts are correct.
   */
  public function testJsAssetsOrder(): void {
    $time = time();
    $assets_a = (new AttachedAssets())
      ->setAlreadyLoadedLibraries([])
      ->setLibraries(['core/drupal', 'core/ckeditor5', 'core/jquery', 'piggy/ckeditor'])
      ->setSettings(['currentTime' => $time]);
    $assets_b = (new AttachedAssets())
      ->setAlreadyLoadedLibraries([])
      ->setLibraries(['piggy/ckeditor', 'core/drupal', 'core/ckeditor5', 'core/jquery'])
      ->setSettings(['currentTime' => $time]);
    $js_assets_a = $this->assetResolver->getJsAssets($assets_a, FALSE, $this->english);
    $js_assets_b = $this->assetResolver->getJsAssets($assets_b, FALSE, $this->english);

    $grouper = new JsCollectionGrouper();

    $group_a = $grouper->group($js_assets_a[1]);
    $group_b = $grouper->group($js_assets_b[1]);

    foreach ($group_a as $key => $value) {
      $this->assertSame($value['items'], $group_b[$key]['items']);
    }
  }

  /**
   * Returns an import map entry.
   *
   * @param string $data
   *   The resolved path or URL.
   * @param int|string $version
   *   The version of the library providing the entry, or -1.
   * @param bool $external
   *   Whether the entry is an external URL.
   *
   * @return array
   *   An import map entry.
   */
  protected function importMapEntry(string $data, int|string $version = -1, bool $external = FALSE): array {
    return [
      'data' => $data,
      'external' => $external,
      'version' => $version,
    ];
  }

  /**
   * Tests get import map.
   */
  public function testGetImportMap(): void {
    $this->modules = ['llama', 'piggy'];
    $this->extensionLibraries = [
      'core' => [
        'core-library' => [
          'importmap' => [
            'imports' => [
              'core-library' => $this->importMapEntry('/core/lib/core-library.mjs', '1.0'),
            ],
            'scopes' => [],
            'integrity' => [],
          ],
        ],
      ],
      'llama' => [
        'llama-library' => [
          'importmap' => [
            'imports' => [
              'llama' => $this->importMapEntry('modules/llama/llama.mjs', '2.0'),
            ],
            'scopes' => [
              '/modules/llama/v1' => [
                'llama' => $this->importMapEntry('modules/llama/v1/llama.mjs', '1.0'),
              ],
            ],
            'integrity' => [
              'modules/llama/llama.mjs' => [
                'data' => 'modules/llama/llama.mjs',
                'external' => FALSE,
                'version' => '2.0',
                'integrity' => 'sha384-llama',
              ],
            ],
          ],
        ],
        'no-import-map' => [
          'importmap' => [
            'imports' => [],
            'scopes' => [],
            'integrity' => [],
          ],
        ],
      ],
      'stark' => [
        'theme-library' => [
          'importmap' => [
            'imports' => [
              'theme' => $this->importMapEntry('https://example.com/theme.mjs', -1, TRUE),
            ],
            'scopes' => [],
            'integrity' => [],
          ],
        ],
      ],
    ];
    $import_map = $this->assetResolver->getImportMap();
    $this->assertSame([
      'imports' => [
        'core-library' => $this->importMapEntry('/core/lib/core-library.mjs', '1.0'),
        'llama' => $this->importMapEntry('modules/llama/llama.mjs', '2.0'),
        'theme' => $this->importMapEntry('https://example.com/theme.mjs', -1, TRUE),
      ],
      'scopes' => [
        '/modules/llama/v1' => [
          'llama' => $this->importMapEntry('modules/llama/v1/llama.mjs', '1.0'),
        ],
      ],
      'integrity' => [
        'modules/llama/llama.mjs' => [
          'data' => 'modules/llama/llama.mjs',
          'external' => FALSE,
          'version' => '2.0',
          'integrity' => 'sha384-llama',
        ],
      ],
    ], $import_map);
  }

  /**
   * Tests that a conflicting import map entry is ignored, and logged.
   */
  public function testGetImportMapWithConflict(): void {
    $this->createAssetResolverWithLogger();
    $this->modules = ['llama', 'piggy'];
    $this->extensionLibraries = [
      'llama' => [
        'llama-library' => [
          'importmap' => [
            'imports' => [
              'llama' => $this->importMapEntry('modules/llama/llama.mjs', '2.0'),
            ],
            'scopes' => [],
            'integrity' => [],
          ],
        ],
      ],
      'piggy' => [
        'piggy-library' => [
          'importmap' => [
            'imports' => [
              'llama' => $this->importMapEntry('modules/piggy/piggy-llama.mjs', '1.0'),
            ],
            'scopes' => [],
            'integrity' => [],
          ],
        ],
      ],
    ];
    $this->logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains("The module specifier 'llama' in the 'imports' section is defined by both llama/llama-library and piggy/piggy-library with different values. The value from llama/llama-library is used."));
    $import_map = $this->assetResolver->getImportMap();
    $this->assertSame($this->importMapEntry('modules/llama/llama.mjs', '2.0'), $import_map['imports']['llama']);
  }

  /**
   * Tests that a duplicate import map entry is not a conflict.
   *
   * The entry maps the same module specifier to the same file, but the
   * library versions differ. The version only affects the query string of
   * the module URL, so this is not a conflict.
   */
  public function testGetImportMapWithDuplicate(): void {
    $this->createAssetResolverWithLogger();
    $this->modules = ['llama', 'piggy'];
    $this->extensionLibraries = [
      'llama' => [
        'llama-library' => [
          'importmap' => [
            'imports' => [
              'llama' => $this->importMapEntry('modules/llama/llama.mjs', '2.0'),
            ],
            'scopes' => [],
            'integrity' => [],
          ],
        ],
      ],
      'piggy' => [
        'piggy-library' => [
          'importmap' => [
            'imports' => [
              'llama' => $this->importMapEntry('modules/llama/llama.mjs', '1.0'),
            ],
            'scopes' => [],
            'integrity' => [],
          ],
        ],
      ],
    ];
    $this->logger->expects($this->never())
      ->method('error');
    $import_map = $this->assetResolver->getImportMap();
    $this->assertCount(1, $import_map['imports']);
  }

  /**
   * Tests get module preloads.
   */
  #[DataProvider('providerGetModulePreloads')]
  public function testGetModulePreloads(array $attached, array $expected): void {
    $assets = (new AttachedAssets())->setAlreadyLoadedLibraries([])->setLibraries($attached);
    $this->assertSame($expected, $this->assetResolver->getModulePreloads($assets));
  }

  public static function providerGetModulePreloads(): array {
    return [
      'no libraries attached' => [[], []],
      'library without import map' => [
        ['piggy/css'],
        [],
      ],
      'library with import map' => [
        ['llama/llama-library'],
        [
          ['data' => 'modules/llama/llama.mjs', 'external' => FALSE, 'version' => '2.0'],
          ['data' => 'https://example.com/llama.mjs', 'external' => TRUE, 'version' => '2.0'],
          ['data' => 'modules/llama/v1/llama.mjs', 'external' => FALSE, 'version' => '1.0'],
        ],
      ],
      'import map pulled in as a dependency' => [
        ['piggy/consumer'],
        [
          ['data' => 'modules/llama/llama.mjs', 'external' => FALSE, 'version' => '2.0'],
          ['data' => 'https://example.com/llama.mjs', 'external' => TRUE, 'version' => '2.0'],
          ['data' => 'modules/llama/v1/llama.mjs', 'external' => FALSE, 'version' => '1.0'],
        ],
      ],
    ];
  }

}

if (!defined('CSS_AGGREGATE_DEFAULT')) {
  define('CSS_AGGREGATE_DEFAULT', 0);
}

if (!defined('JS_DEFAULT')) {
  define('JS_DEFAULT', 0);
}

/**
 * Stub class with memory cache implementation for testing.
 */
class TestMemoryBackend extends MemoryBackend {

  public function getAllCids(): array {
    return array_keys($this->cache);
  }

}
