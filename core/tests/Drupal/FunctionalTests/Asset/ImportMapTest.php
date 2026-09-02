<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Asset;

use Behat\Mink\Element\NodeElement;
use Drupal\Component\Serialization\Json;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the import map functionality of the libraries API.
 */
#[Group('asset')]
#[RunTestsInSeparateProcesses]
class ImportMapTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['importmap_test'];

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fileUrlGenerator = $this->container->get('file_url_generator');
  }

  /**
   * Returns the module path of the importmap_test module.
   *
   * @return string
   *   The module path.
   */
  protected function modulePath(): string {
    return $this->container->get('extension.list.module')->getPath('importmap_test');
  }

  /**
   * Returns the expected import map, as emitted in the HTML head.
   *
   * @return array
   *   The import map JSON, decoded.
   */
  protected function expectedImportMap(): array {
    $path = $this->modulePath();
    $foo = $this->fileUrlGenerator->generate("$path/js/foo.js")->toString() . '?v=1.0';
    $foo_scoped = $this->fileUrlGenerator->generate("$path/js/scope/foo.js")->toString() . '?v=1.0';
    return [
      'imports' => [
        'importmap-test-foo' => $foo,
        'importmap-test-prefix/' => $this->fileUrlGenerator->generate("$path/js/prefix/")->toString() . '?v=1.0',
        'importmap-test-external' => 'https://example.com/importmap-test-external.mjs',
      ],
      'scopes' => [
        $this->fileUrlGenerator->generate("$path/js/scope/")->toString() => [
          'importmap-test-foo' => $foo_scoped,
        ],
      ],
      'integrity' => [
        $foo => 'sha384-oXiGjHO+NOxR1XH8somfajtqMWa5PTleNh5VGncEbQQTrZZboU8xIMhoDzGBbroU',
      ],
    ];
  }

  /**
   * Returns the import map script element from the page.
   *
   * @return NodeElement|null
   *   The import map script element, or NULL if it is not present.
   */
  protected function importMapScript(): ?NodeElement {
    $elements = $this->getSession()->getPage()->findAll('css', 'script[type="importmap"]');
    return $elements ? $elements[0] : NULL;
  }

  /**
   * Tests that the import map of all known libraries is emitted in the head.
   */
  public function testImportMapInHead(): void {
    $this->drupalGet('<front>');
    $script = $this->importMapScript();
    $this->assertNotNull($script, 'The import map script is present in the head.');
    $this->assertSame($this->expectedImportMap(), Json::decode($script->getText()));
    // The import map script is in the head, and no module script or module
    // preload comes before it.
    $content = $this->getSession()->getPage()->getContent();
    $head_start = strpos($content, '<head>');
    $head_end = strpos($content, '</head>');
    $importmap_start = strpos($content, '<script type="importmap">');
    $this->assertNotFalse($importmap_start);
    $this->assertGreaterThan($head_start, $importmap_start);
    $this->assertLessThan($head_end, $importmap_start, 'The import map script is in the head.');
    $this->assertDoesNotMatchRegularExpression('/<script[^>]*type="module"[^>]*>|<link[^>]*rel="modulepreload"[^>]*>/', substr($content, 0, $importmap_start), 'No module scripts or module preloads come before the import map script.');
    // No module preloads, since no library with import map entries is
    // attached.
    $this->assertCount(0, $this->getSession()->getPage()->findAll('css', 'link[rel="modulepreload"]'));
  }

  /**
   * Tests that the entries of attached libraries are preloaded.
   */
  public function testImportMapPreloads(): void {
    $this->drupalGet('/importmap-test/consumer');
    $script = $this->importMapScript();
    $this->assertNotNull($script, 'The import map script is present in the head.');
    $this->assertSame($this->expectedImportMap(), Json::decode($script->getText()));
    $path = $this->modulePath();
    $expected_preloads = [
      $this->fileUrlGenerator->generate("$path/js/foo.js")->toString() . '?v=1.0',
      'https://example.com/importmap-test-external.mjs',
      $this->fileUrlGenerator->generate("$path/js/scope/foo.js")->toString() . '?v=1.0',
    ];
    $preloads = $this->getSession()->getPage()->findAll('css', 'link[rel="modulepreload"]');
    $this->assertCount(count($expected_preloads), $preloads);
    foreach ($preloads as $index => $preload) {
      $this->assertSame($expected_preloads[$index], $preload->getAttribute('href'));
    }
    // The external preload is a cross-origin preload.
    $this->assertSame('anonymous', $preloads[1]->getAttribute('crossorigin'));
    // The modulepreload links come after the import map script, and before
    // the consumer module script.
    $content = $this->getSession()->getPage()->getContent();
    $importmap_start = strpos($content, '<script type="importmap">');
    $preload_start = strpos($content, '<link rel="modulepreload"');
    $consumer_start = strpos($content, 'js/consumer.js');
    $this->assertLessThan($preload_start, $importmap_start, 'The import map script comes before the module preloads.');
    $this->assertLessThan($consumer_start, $preload_start, 'The module preloads come before the consumer module script.');
    // The consumer module script is an ES module.
    $consumer = $this->getSession()->getPage()->find('css', 'script[type="module"][src*="js/consumer.js"]');
    $this->assertNotNull($consumer, 'The consumer script is an ES module.');
  }

  /**
   * Tests that an HTMX response adds module preloads, but not an import map.
   *
   * The consumer route has the '_htmx_route' option, so it returns an HTMX
   * response rendered by
   * \Drupal\Core\Render\MainContent\HtmxRenderer. A page only supports a
   * single import map, which is sent with the main HTML document. An HTMX
   * response can only add module preloads for the libraries attached to the
   * response; the HTMX client adds them to the head. See
   * core/misc/htmx/htmx-assets.js.
   */
  public function testHtmxResponseAddsModulePreloadsAndNotImportMap(): void {
    $content = $this->drupalGet('/importmap-test/htmx-consumer');
    $this->assertStringNotContainsString('<script type="importmap"', $content, 'The HTMX response does not contain an import map.');
    $path = $this->modulePath();
    $expected_preloads = [
      $this->fileUrlGenerator->generate("$path/js/foo.js")->toString() . '?v=1.0',
      'https://example.com/importmap-test-external.mjs',
      $this->fileUrlGenerator->generate("$path/js/scope/foo.js")->toString() . '?v=1.0',
    ];
    $preloads = $this->getSession()->getPage()->findAll('css', 'link[rel="modulepreload"]');
    $this->assertCount(count($expected_preloads), $preloads);
    foreach ($preloads as $index => $preload) {
      $this->assertSame($expected_preloads[$index], $preload->getAttribute('href'));
    }
    // The module preloads come before the consumer module script, so the
    // modules can be fetched as early as possible.
    $preload_start = strpos($content, '<link rel="modulepreload"');
    $consumer_start = strpos($content, 'js/consumer.js');
    $this->assertNotFalse($preload_start);
    $this->assertNotFalse($consumer_start);
    $this->assertLessThan($consumer_start, $preload_start, 'The module preloads come before the consumer module script.');
  }

  /**
   * Tests that the scoped consumer gets the scoped import map entry.
   */
  public function testImportMapScopedConsumer(): void {
    $this->drupalGet('/importmap-test/scoped-consumer');
    $script = $this->importMapScript();
    $this->assertNotNull($script, 'The import map script is present in the head.');
    $this->assertSame($this->expectedImportMap(), Json::decode($script->getText()));
  }

  /**
   * Tests that an Ajax response adds module preloads, but not an import map.
   *
   * A page only supports a single import map, which is sent with the main
   * HTML document. An Ajax response can only add module preloads for the
   * libraries attached to the response.
   */
  public function testAjaxResponseAddsModulePreloadsAndNotImportMap(): void {
    $content = $this->drupalGet('/importmap-test/consumer', [
      'query' => ['_drupal_ajax' => 1, '_wrapper_format' => 'drupal_ajax'],
    ]);
    $this->assertStringNotContainsString('<script type="importmap"', $content, 'The Ajax response does not contain an import map.');
    $commands = Json::decode($content);
    $this->assertIsArray($commands);
    $preload_index = NULL;
    $add_js_index = NULL;
    foreach ($commands as $index => $command) {
      if (($command['command'] ?? NULL) === 'add_module_preload') {
        $preload_index = $index;
      }
      elseif (($command['command'] ?? NULL) === 'add_js') {
        $add_js_index = $index;
      }
    }
    $this->assertNotNull($preload_index, 'The Ajax response adds module preloads for the attached libraries.');
    $this->assertNotNull($add_js_index, 'The Ajax response adds the attached JavaScript.');
    $this->assertLessThan($add_js_index, $preload_index, 'The module preloads are added before the attached JavaScript, so the modules can be fetched as early as possible.');
    $path = $this->modulePath();
    $this->assertSame([
      'command' => 'add_module_preload',
      'data' => [
        [
          'rel' => 'modulepreload',
          'href' => $this->fileUrlGenerator->generate("$path/js/foo.js")->toString() . '?v=1.0',
        ],
        [
          'rel' => 'modulepreload',
          'href' => 'https://example.com/importmap-test-external.mjs',
          'crossorigin' => 'anonymous',
        ],
        [
          'rel' => 'modulepreload',
          'href' => $this->fileUrlGenerator->generate("$path/js/scope/foo.js")->toString() . '?v=1.0',
        ],
      ],
    ], $commands[$preload_index]);
  }

}
