<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Asset;

use Drupal\Component\Datetime\Time;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Asset\ImportMapBuilder;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests the aggregation of the import map definitions of all known libraries.
 *
 * @see \Drupal\Core\Asset\ImportMapBuilder
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(ImportMapBuilder::class)]
#[Group('Asset')]
class ImportMapBuilderTest extends UnitTestCase {

  /**
   * The library definitions, keyed by extension.
   *
   * @var array[]
   */
  protected array $extensionLibraries = [];

  /**
   * The machine names of the enabled modules.
   *
   * @var string[]
   */
  protected array $modules = [];

  /**
   * The library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected LibraryDiscoveryInterface&MockObject $libraryDiscovery;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ModuleHandlerInterface&MockObject $moduleHandler;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ThemeManagerInterface&MockObject $themeManager;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected StateInterface&MockObject $state;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\MemoryBackend
   */
  protected MemoryBackend $cache;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerInterface&MockObject $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->libraryDiscovery = $this->createMock(LibraryDiscoveryInterface::class);
    $this->libraryDiscovery
      ->method('getLibrariesByExtension')
      ->willReturnCallback(fn(string $extension) => $this->extensionLibraries[$extension] ?? []);

    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->moduleHandler
      ->method('getModuleList')
      ->willReturnCallback(fn() => array_combine($this->modules, array_fill(0, count($this->modules), NULL)));
    $this->moduleHandler
      ->method('invokeAllWith')
      ->with('importmap_alter');

    $this->themeManager = $this->createMock(ThemeManagerInterface::class);
    $this->themeManager
      ->method('getActiveTheme')
      ->willReturn(new ActiveTheme(['name' => 'stark']));

    $this->state = $this->createMock(StateInterface::class);
    $this->cache = new MemoryBackend(new Time());
    $this->logger = $this->createMock(LoggerInterface::class);
  }

  /**
   * Returns the import map builder under test.
   *
   * @return \Drupal\Core\Asset\ImportMapBuilder
   *   The import map builder.
   */
  protected function createBuilder(): ImportMapBuilder {
    return new ImportMapBuilder($this->libraryDiscovery, $this->moduleHandler, $this->themeManager, $this->state, $this->cache, $this->logger);
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
   * @param bool $cache
   *   Whether the module can be cached.
   *
   * @return array
   *   An import map entry.
   */
  protected function importMapEntry(string $data, int|string $version = -1, bool $external = FALSE, bool $cache = TRUE): array {
    return [
      'data' => $data,
      'external' => $external,
      'version' => $version,
      'cache' => $cache,
    ];
  }

  /**
   * Tests the aggregation of the libraries of all known extensions.
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
                'cache' => TRUE,
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
    $import_map = $this->createBuilder()->getImportMap();
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
          'cache' => TRUE,
          'integrity' => 'sha384-llama',
        ],
      ],
    ], $import_map);
  }

  /**
   * Tests that the aggregated map is written to the cache and reused.
   */
  public function testImportMapIsCached(): void {
    $this->modules = ['llama'];
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
    ];

    $builder = $this->createBuilder();
    $map = $builder->getImportMap();
    $provenance = $builder->getProvenance();

    // The cache key holds the active theme and every known extension, so that
    // installing or uninstalling an extension is picked up.
    $cid = 'importmap:stark:' . Crypt::hashBase64('core,llama,stark');
    $cached = $this->cache->get($cid);
    $this->assertNotNull($cached, 'The built map is written to the cache.');
    $this->assertSame([
      'map' => $map,
      'provenance' => $provenance,
    ], $cached->data, 'The provenance is cached alongside the map.');
    $this->assertSame(['library_info'], $cached->tags);

    // A later request is served from the cache. Reading the libraries of every
    // known extension again would invoke hook_library_info_alter() and both
    // implementations of hook_importmap_alter() on every page view.
    $this->extensionLibraries = [];
    $this->libraryDiscovery->expects($this->never())
      ->method('getLibrariesByExtension');
    $this->moduleHandler->expects($this->never())
      ->method('alter');
    $this->themeManager->expects($this->never())
      ->method('alter');
    $this->assertSame($map, $this->createBuilder()->getImportMap());
  }

  /**
   * Tests that invalidating 'library_info' discards the cached map.
   *
   * The map is derived from library definitions, so an extension that changes
   * one invalidates the 'library_info' tag, and the map has to be rebuilt.
   */
  public function testCachedImportMapIsInvalidatedWithLibraryInfo(): void {
    $this->modules = ['llama'];
    $this->extensionLibraries = [
      'llama' => [
        'llama-library' => [
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
    $builder = $this->createBuilder();
    $this->assertSame('modules/llama/llama.mjs', $builder->getImportMap()['imports']['llama']['data']);

    $this->cache->invalidateTags(['library_info']);
    $this->extensionLibraries['llama']['llama-library']['importmap']['imports']['llama'] = $this->importMapEntry('modules/llama/llama-2.0.mjs', '2.0');

    $this->assertSame(
      'modules/llama/llama-2.0.mjs',
      $this->createBuilder()->getImportMap()['imports']['llama']['data'],
      'The map is rebuilt from the changed library definition.'
    );
  }

  /**
   * Tests that a conflicting import map entry is ignored, and logged.
   */
  public function testGetImportMapWithConflict(): void {
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
    $import_map = $this->createBuilder()->getImportMap();
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
    $import_map = $this->createBuilder()->getImportMap();
    $this->assertCount(1, $import_map['imports']);
  }

  /**
   * Tests that the merge order does not depend on the module install order.
   *
   * Two modules define the same module specifier. The install order puts 'zzz'
   * first, but the merge order sorts the modules by machine name, so two sites
   * with the same modules enabled always resolve the conflict the same way.
   */
  public function testConflictResolutionDoesNotDependOnInstallOrder(): void {
    $this->modules = ['zzz', 'aaa'];
    $this->extensionLibraries = [
      'zzz' => [
        'llama' => [
          'importmap' => [
            'imports' => [
              'llama' => $this->importMapEntry('modules/zzz/zzz.mjs', '1.0'),
            ],
            'scopes' => [],
            'integrity' => [],
          ],
        ],
      ],
      'aaa' => [
        'llama' => [
          'importmap' => [
            'imports' => [
              'llama' => $this->importMapEntry('modules/aaa/aaa.mjs', '1.0'),
            ],
            'scopes' => [],
            'integrity' => [],
          ],
        ],
      ],
    ];
    $import_map = $this->createBuilder()->getImportMap();
    $this->assertSame('modules/aaa/aaa.mjs', $import_map['imports']['llama']['data']);
  }

  /**
   * Tests that the import map is not built in a restricted context.
   *
   * @param bool $maintenance_mode
   *   Whether the site is in maintenance mode.
   */
  #[DataProvider('providerRestrictedContext')]
  public function testImportMapIsNotBuiltInRestrictedContext(bool $maintenance_mode): void {
    $this->modules = ['llama'];
    $this->extensionLibraries = [
      'llama' => [
        'llama' => [
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
    $this->state->method('get')
      ->with('system.maintenance_mode')
      ->willReturn($maintenance_mode);
    // The libraries of the enabled modules must not be read at all: that would
    // invoke hook_library_info_alter() during installation or in maintenance
    // mode, where its assumptions about the site may not hold.
    $this->libraryDiscovery->expects($this->never())
      ->method('getLibrariesByExtension');
    $builder = $this->createBuilder();
    $this->assertSame([
      'imports' => [],
      'scopes' => [],
      'integrity' => [],
    ], $builder->getImportMap());
    $this->assertSame([
      'imports' => [],
      'scopes' => [],
      'integrity' => [],
    ], $builder->getProvenance());
  }

  /**
   * Returns data for testing restricted contexts.
   *
   * @return array[]
   *   Test cases, each providing whether the site is in maintenance mode.
   */
  public static function providerRestrictedContext(): array {
    return [
      'maintenance mode' => [TRUE],
    ];
  }

  public function testConflictWithoutProvenanceDoesNotBreakThePage(): void {
    $this->modules = ['llama'];
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
    ];
    // A hook_importmap_alter() implementation must only remove conflicts, but a
    // faulty one can add a key that no library definition provides. That must
    // not raise an error, because the import map is rendered on every page.
    $this->moduleHandler->method('alter')
      ->willReturnCallback(function (string $hook, array &$map, array &$conflicts): void {
        $conflicts['imports']['bogus'] = ['some/module' => $this->importMapEntry('modules/some/module.mjs', '1.0')];
        $conflicts['scopes']['js/bogus/']['scoped-bogus'] = ['some/module' => $this->importMapEntry('modules/some/scoped.mjs', '1.0')];
        $conflicts['integrity']['bogus.js'] = [
          'some/module' => [
            'data' => 'modules/some/module.mjs',
            'external' => FALSE,
            'version' => '1.0',
            'cache' => TRUE,
            'integrity' => 'sha384-x',
          ],
        ];
      });
    $messages = [];
    $this->logger->method('error')
      ->willReturnCallback(function (string $message) use (&$messages): void {
        $messages[] = $message;
      });
    $import_map = $this->createBuilder()->getImportMap();
    $this->assertSame(['llama'], array_keys($import_map['imports']));
    $this->assertCount(3, $messages);
    $this->assertStringContainsString("'bogus'", $messages[0]);
    $this->assertStringContainsString("'scoped-bogus'", $messages[1]);
    $this->assertStringContainsString("'bogus.js'", $messages[2]);
  }

  #[DataProvider('providerAlteredConflict')]
  public function testConflictLoggedAfterTheMapWasAltered(callable $alter, string $expected_message): void {
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
    $this->moduleHandler->method('alter')
      ->willReturnCallback($alter);
    $this->logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains($expected_message));
    $this->createBuilder()->getImportMap();
  }

  /**
   * Returns data for testing a conflict logged after the map was altered.
   *
   * @return array[]
   *   Test cases, each providing an implementation of hook_importmap_alter()
   *   and the message fragment that must be logged.
   */
  public static function providerAlteredConflict(): array {
    // The conflict is left in place, so the message can name the definition
    // that the merged map uses.
    $untouched = static function (string $hook, array &$map, array &$conflicts): void {};
    // hook_importmap_alter() replaces the entry the merge picked, so the
    // message must not claim that the merged value is used.
    $replaced = static function (string $hook, array &$map, array &$conflicts): void {
      $map['imports']['llama'] = [
        'data' => 'modules/other/other.mjs',
        'external' => FALSE,
        'version' => '1.0',
        'cache' => TRUE,
      ];
    };
    // hook_importmap_alter() removes the entry entirely.
    $removed = static function (string $hook, array &$map, array &$conflicts): void {
      unset($map['imports']['llama']);
    };
    return [
      'not altered' => [$untouched, 'The value from llama/llama-library is used.'],
      'replaced by the alter hook' => [
        $replaced,
        'The value from llama/llama-library was replaced by hook_importmap_alter().',
      ],
      'removed by the alter hook' => [
        $removed,
        'The value from llama/llama-library was removed by hook_importmap_alter().',
      ],
    ];
  }

}
