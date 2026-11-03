<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Asset;

use Drupal\Core\Asset\AttachedAssets;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a module is reachable under a single URL.
 *
 * Browsers identify an ES module by its resolved URL. A file that is attached
 * as a JavaScript asset and also mapped by the import map must therefore be
 * given exactly the same URL by the JavaScript collection renderer, the import
 * map renderer and the module preload renderer. Otherwise the browser fetches
 * the file twice and instantiates the module twice, with separate module state,
 * which breaks modules that keep state or register custom elements.
 *
 * The test module importmap_test_asset declares files both as JavaScript assets
 * and as import map entries, so the URLs are derived from real library
 * definitions by the real services. The query string formula itself is covered
 * by \Drupal\Tests\Core\Asset\ImportMapUrlTest; what is verified here is the
 * plumbing between a library definition and each of the three renderers.
 */
#[Group('Asset')]
#[RunTestsInSeparateProcesses]
class ImportMapUrlParityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['importmap_test_asset'];

  /**
   * The asset resolver service.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->assetResolver = $this->container->get('asset.resolver');
  }

  /**
   * Tests that an asset, an import map entry and a preload agree on the URL.
   *
   * @param string $library
   *   The name of the library declaring the file as an asset and as an entry.
   * @param string $specifier
   *   The module specifier of the import map entry.
   * @param string $file
   *   The file, relative to the test module.
   * @param string $invalidated_by
   *   What the URL of the file is expected to be invalidated by: either its
   *   library version, or the request time when it must not be cached.
   */
  #[DataProvider('providerMappedModules')]
  public function testAssetImportMapAndPreloadUrlsMatch(string $library, string $specifier, string $file, string $invalidated_by): void {
    $data = $this->modulePath() . '/' . $file;
    $assets = (new AttachedAssets())->setLibraries(["importmap_test_asset/$library"]);

    // The URL of the file when it is attached as a JavaScript asset.
    [$header, $footer] = $this->assetResolver->getJsAssets($assets, FALSE);
    $js_assets = array_values(array_filter(
      array_merge($header, $footer),
      static fn(array $options) => ($options['data'] ?? NULL) === $data,
    ));
    $this->assertCount(1, $js_assets, "The file of the $library library is attached as a JavaScript asset.");
    $src = $this->container->get('asset.js.collection_renderer')->render($js_assets)[0]['#attributes']['src'];

    // The URL of the same file when it is reached through the import map.
    $import_map = $this->container->get('asset.importmap.renderer')->render($this->assetResolver->getImportMap());
    $mapped = json_decode($import_map['importmap']['#value'], TRUE)['imports'][$specifier];

    // And when it is preloaded for the libraries attached to the response.
    $preloads = $this->container->get('asset.modulepreload.renderer')->render($this->assetResolver->getModulePreloads($assets));
    $preloaded = $preloads['modulepreload:' . $data]['#attributes']['href'];

    $this->assertSame($this->expectedUrl($data, $invalidated_by), $src, 'The script tag carries the expected query string.');
    $this->assertSame($src, $mapped, "The import map entry for '$specifier' resolves to the URL of the attached JavaScript asset.");
    $this->assertSame($src, $preloaded, "The module preload for '$file' points at the URL of the attached JavaScript asset.");
  }

  /**
   * Tests that 'cache: false' has to be repeated on the import map entry.
   *
   * The 'cache' option of an import map entry defaults to TRUE and is not
   * inherited from the 'cache' option of the JavaScript file, because a library
   * can map a file that it does not declare as an asset at all. A library that
   * marks its module file with 'cache: false' but does not say so on the entry
   * therefore produces two URLs for the same file, and the browser instantiates
   * the module twice. The not-mirrored library of importmap_test_asset is that
   * mistake, and is asserted here so that the consequence is visible: if the
   * option is ever inherited, this test fails and the fixture should then be
   * updated to assert parity instead.
   */
  public function testCacheOptionIsNotInherited(): void {
    $data = $this->modulePath() . '/js/not-mirrored.js';
    $assets = (new AttachedAssets())->setLibraries(['importmap_test_asset/not-mirrored']);

    [$header, $footer] = $this->assetResolver->getJsAssets($assets, FALSE);
    $js_assets = array_values(array_filter(
      array_merge($header, $footer),
      static fn(array $options) => ($options['data'] ?? NULL) === $data,
    ));
    $src = $this->container->get('asset.js.collection_renderer')->render($js_assets)[0]['#attributes']['src'];

    $import_map = $this->container->get('asset.importmap.renderer')->render($this->assetResolver->getImportMap());
    $mapped = json_decode($import_map['importmap']['#value'], TRUE)['imports']['importmap-test-not-mirrored'];

    $this->assertSame($this->expectedUrl($data, 'time'), $src, 'The JavaScript asset is invalidated by the request time.');
    $this->assertSame($this->expectedUrl($data, 'version'), $mapped, 'The import map entry is invalidated by the version, so the module is fetched a second time.');
    $this->assertNotSame($src, $mapped, 'An entry that does not repeat cache: false resolves to a different URL than the asset it maps.');
  }

  /**
   * Returns the path of the test module, relative to the Drupal root.
   *
   * @return string
   *   The path of the module.
   */
  protected function modulePath(): string {
    return $this->container->get('extension.list.module')->getPath('importmap_test_asset');
  }

  /**
   * Returns the URL a file of the test module is expected to be given.
   *
   * @param string $data
   *   The file, relative to the Drupal root.
   * @param string $invalidated_by
   *   Either 'version', to invalidate the URL with the library version, or
   *   'time', to invalidate it with the request time.
   *
   * @return string
   *   The expected URL.
   */
  protected function expectedUrl(string $data, string $invalidated_by): string {
    $url = $this->container->get('file_url_generator')->generate($data)->toString();
    return $url . ($invalidated_by === 'time' ? '?' . $this->container->get('datetime.time')->getRequestTime() : '?v=1.0');
  }

  /**
   * Provides the libraries whose files are both assets and import map entries.
   *
   * @return array[]
   *   Test cases.
   */
  public static function providerMappedModules(): array {
    return [
      'versioned module' => ['cached', 'importmap-test-asset', 'js/cached.js', 'version'],
      'module that must not be cached' => ['not-cached', 'importmap-test-not-cached', 'js/not-cached.js', 'time'],
    ];
  }

}
