<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Asset;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Asset\ImportMapRenderer;
use Drupal\Core\Asset\JsCollectionRenderer;
use Drupal\Core\Asset\ModulePreloadRenderer;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that import map URLs match the URLs of JavaScript assets.
 *
 * Browsers identify an ES module by its resolved URL. A file that is attached
 * as a JavaScript asset and also mapped by the import map must therefore be
 * given the same URL by both renderers, or the module is fetched twice and
 * instantiated twice, with separate module state. The query string is what makes
 * or breaks that, so the two renderers are compared side by side here.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(ImportMapRenderer::class)]
#[CoversClass(ModulePreloadRenderer::class)]
#[Group('Asset')]
class ImportMapUrlTest extends UnitTestCase {

  /**
   * The asset query string returned by the stub.
   */
  protected const ASSET_QUERY_STRING = 'abc123';

  /**
   * The request time returned by the stub.
   */
  protected const REQUEST_TIME = 1700000000;

  /**
   * Returns a file URL generator that prefixes paths with a fixed origin.
   *
   * @return \Drupal\Core\File\FileUrlGeneratorInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The stubbed file URL generator.
   */
  protected function getFileUrlGenerator(): FileUrlGeneratorInterface {
    $file_url_generator = $this->createMock(FileUrlGeneratorInterface::class);
    $url = static fn(string $path): string => 'https://example.com/' . ltrim($path, '/');
    $file_url_generator->method('generate')
      ->willReturnCallback(static fn(string $path) => new class($url($path)) extends Url {

        /**
         * Constructs a URL object with a fixed string representation.
         */
        public function __construct(protected string $generated) {
        }

        /**
         * {@inheritdoc}
         */
        public function toString($collect_bubbleable_metadata = FALSE) {
          return $this->generated;
        }

      });
    $file_url_generator->method('generateString')
      ->willReturnCallback($url);
    return $file_url_generator;
  }

  /**
   * Returns the import map renderer under test.
   *
   * @return \Drupal\Core\Asset\AssetCollectionRendererInterface
   *   The import map renderer.
   */
  protected function getImportMapRenderer(): AssetCollectionRendererInterface {
    return new ImportMapRenderer($this->getFileUrlGenerator(), $this->getAssetQueryString(), $this->getTime());
  }

  /**
   * Returns the module preload renderer under test.
   *
   * @return \Drupal\Core\Asset\AssetCollectionRendererInterface
   *   The module preload renderer.
   */
  protected function getModulePreloadRenderer(): AssetCollectionRendererInterface {
    return new ModulePreloadRenderer($this->getFileUrlGenerator(), $this->getAssetQueryString(), $this->getTime());
  }

  /**
   * Returns the JavaScript collection renderer to compare against.
   *
   * @return \Drupal\Core\Asset\JsCollectionRenderer
   *   The JavaScript collection renderer.
   */
  protected function getJsCollectionRenderer(): JsCollectionRenderer {
    return new JsCollectionRenderer($this->getAssetQueryString(), $this->getFileUrlGenerator(), $this->getTime());
  }

  /**
   * Returns a stubbed asset query string.
   *
   * @return \Drupal\Core\Asset\AssetQueryStringInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The asset query string.
   */
  protected function getAssetQueryString(): AssetQueryStringInterface {
    $asset_query_string = $this->createMock(AssetQueryStringInterface::class);
    $asset_query_string->method('get')->willReturn(static::ASSET_QUERY_STRING);
    return $asset_query_string;
  }

  /**
   * Returns a stubbed time.
   *
   * @return \Drupal\Component\Datetime\TimeInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The time.
   */
  protected function getTime(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(static::REQUEST_TIME);
    return $time;
  }

  /**
   * Tests that an import map entry and a module preload give the same URL.
   *
   * @param string $data
   *   The path of the module.
   * @param int|string $version
   *   The library version, or -1 when the library has no version.
   * @param bool $cache
   *   Whether the module can be cached.
   *
   * @see \Drupal\Core\Asset\ImportMapUrlTrait::importMapEntryUrl()
   */
  #[DataProvider('providerModuleUrls')]
  public function testImportMapAndPreloadUseTheSameUrl(string $data, int|string $version, bool $cache): void {
    $entry = [
      'data' => $data,
      'external' => FALSE,
      'version' => $version,
      'cache' => $cache,
    ];
    $import_map = $this->getImportMapRenderer()->render([
      'imports' => ['llama' => $entry],
      'scopes' => [],
      'integrity' => [],
    ]);
    $preloads = $this->getModulePreloadRenderer()->render([$entry]);
    $expected = $this->expectedUrl($data, $version, $cache);

    $decoded = json_decode($import_map['importmap']['#value'], TRUE);
    $this->assertSame(['imports' => ['llama' => $expected]], $decoded);
    $this->assertSame($expected, $preloads['modulepreload:' . $data]['#attributes']['href']);
  }

  /**
   * Tests that both renderers agree with the JavaScript collection renderer.
   *
   * This is the invariant that keeps a module from being instantiated twice:
   * <script src> and the import map entry for the same file must be identical.
   */
  #[DataProvider('providerModuleUrls')]
  public function testModuleUrlMatchesTheScriptTagUrl(string $data, int|string $version, bool $cache): void {
    $entry = [
      'data' => $data,
      'external' => FALSE,
      'version' => $version,
      'cache' => $cache,
    ];
    $import_map = $this->getImportMapRenderer()->render([
      'imports' => ['llama' => $entry],
      'scopes' => [],
      'integrity' => [],
    ]);
    $decoded = json_decode($import_map['importmap']['#value'], TRUE);

    // An unaggregated JavaScript asset, as AssetResolver produces it when JS
    // aggregation is disabled, or for a file that cannot be aggregated.
    $scripts = $this->getJsCollectionRenderer()->render([
      [
        'type' => 'file',
        'data' => $data,
        'version' => $version,
        'cache' => $cache,
        'group' => JS_DEFAULT,
      ],
    ]);

    $this->assertSame(
      $scripts[0]['#attributes']['src'],
      $decoded['imports']['llama'],
      'The import map entry resolves to the same URL as the script tag.'
    );

    $preloads = $this->getModulePreloadRenderer()->render([$entry]);
    $this->assertSame(
      $scripts[0]['#attributes']['src'],
      $preloads['modulepreload:' . $data]['#attributes']['href'],
      'The module preload points at the same URL as the script tag.'
    );
  }

  /**
   * Returns the URL a module is expected to be given.
   *
   * @param string $data
   *   The path of the module.
   * @param int|string $version
   *   The library version, or -1.
   * @param bool $cache
   *   Whether the module can be cached.
   *
   * @return string
   *   The expected URL.
   */
  protected function expectedUrl(string $data, int|string $version, bool $cache): string {
    return 'https://example.com/' . $data . $this->expectedQueryString($version, $cache);
  }

  /**
   * Returns the query string a module URL is expected to carry.
   *
   * @param int|string $version
   *   The library version, or -1.
   * @param bool $cache
   *   Whether the module can be cached.
   *
   * @return string
   *   The expected query string, including the leading question mark.
   */
  protected function expectedQueryString(int|string $version, bool $cache): string {
    if (!$cache) {
      return '?' . static::REQUEST_TIME;
    }
    return '?' . ($version === -1 ? static::ASSET_QUERY_STRING : 'v=' . $version);
  }

  /**
   * Provides module paths with version and cacheability combinations.
   *
   * @return array[]
   *   Test cases.
   */
  public static function providerModuleUrls(): array {
    return [
      'versioned library' => ['modules/llama/llama.mjs', '2.0', TRUE],
      'library without a version' => ['modules/llama/llama.mjs', -1, TRUE],
      // Mirrors core/misc/batch.js, which is declared with 'cache: false' in
      // core.libraries.yml.
      'not cacheable' => ['core/misc/batch.js', '2.0', FALSE],
      'not cacheable, no version' => ['core/misc/batch.js', -1, FALSE],
    ];
  }

}

if (!defined('JS_DEFAULT')) {
  define('JS_DEFAULT', 0);
}
