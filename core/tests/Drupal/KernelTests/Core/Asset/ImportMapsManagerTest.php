<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Asset;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Asset\ImportMapsManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests ImportMapsManager.
 *
 * @group importmaps
 * @covers \Drupal\Core\Asset\ImportMapsManager
 */
final class ImportMapsManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'importmaps_test',
    'importmaps_test_imports_only',
    'importmaps_test_scopes_only',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install([
      'importmaps_test_theme',
      'importmaps_test_base_theme',
    ]);
    /** @var \Drupal\Core\Theme\ThemeInitializationInterface $theme_initializer */
    $theme_initializer = $this->container->get(ThemeInitializationInterface::class);
    /** @var \Drupal\Core\Theme\ThemeManagerInterface $theme_manager */
    $theme_manager = $this->container->get(ThemeManagerInterface::class);
    $theme_manager->setActiveTheme($theme_initializer->getActiveThemeByName('importmaps_test_theme'));
  }

  /**
   * Tests import map discovery.
   */
  public function testImportDiscovery(): void {
    /** @var \Drupal\Core\Asset\ImportMapsManagerInterface $manager */
    $manager = $this->container->get(ImportMapsManagerInterface::class);

    $extension_path_resolver = $this->container->get(ExtensionPathResolver::class);
    $import_maps_test_path = $extension_path_resolver->getPath('module', 'importmaps_test');
    $import_maps_test_imports_only_path = $extension_path_resolver->getPath('module', 'importmaps_test_imports_only');
    $import_maps_test_scopes_only_path = $extension_path_resolver->getPath('module', 'importmaps_test_scopes_only');
    $import_maps_test_theme_path = $extension_path_resolver->getPath('theme', 'importmaps_test_theme');
    $import_maps_test_base_theme_path = $extension_path_resolver->getPath('theme', 'importmaps_test_base_theme');

    $basePath = $this->container->get(RequestStack::class)->getCurrentRequest()->getBasePath();

    $base_import_maps = [
      'imports' => [
        'wow' => "{$basePath}/$import_maps_test_base_theme_path/js/wow.js",
        'absolute' => "{$basePath}/dist/absolute.js",
        'url' => "https://example.com/url.js",
        'bar' => "{$basePath}/$import_maps_test_path/js/bar.js",
        'whiz' => "{$basePath}/$import_maps_test_imports_only_path/js/whiz.js",
      ],
      'scopes' => [
        "{$basePath}/$import_maps_test_path/js/scope/" => [
          'bar' => "{$basePath}/$import_maps_test_path/js/bar-scoped.js",
        ],
        "{$basePath}/$import_maps_test_scopes_only_path/js/bar/" => [
          'whiz' => "{$basePath}/$import_maps_test_scopes_only_path/js/bar/whiz.js",
        ],
        "{$basePath}/absolute/js/bar/" => [
          'whiz' => "{$basePath}/absolute/library/whiz.js",
        ],
      ],
    ];

    $import_maps = $manager->getImportMapForTheme('importmaps_test_base_theme');
    self::assertEquals($base_import_maps, $import_maps);

    $theme_imports = NestedArray::mergeDeep($base_import_maps, [
      'imports' => [
        'baz' => "{$basePath}/$import_maps_test_theme_path/js/baz.js",
        // @see \importmaps_test_importmaps_alter()
        'foo' => "{$basePath}/absolute/js/foo.js",
      ],
      'scopes' => [
        "{$basePath}/$import_maps_test_theme_path/js/whiz/" => [
          'baz' => "{$basePath}/$import_maps_test_theme_path/js/whiz/baz.js",
        ],
      ],
    ]);

    $import_maps = $manager->getImportMapForTheme('importmaps_test_theme');
    self::assertEquals($theme_imports, $import_maps);
  }

}
