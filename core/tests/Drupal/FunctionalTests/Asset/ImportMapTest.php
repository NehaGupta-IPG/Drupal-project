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
    $this->assertDoesNotMatchRegularExpression('/<script[^>]*type="module"[^>]*>/', substr($content, 0, $importmap_start), 'No module scripts come before the import map script.');
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
   * Tests that the scoped consumer gets the scoped import map entry.
   */
  public function testImportMapScopedConsumer(): void {
    $this->drupalGet('/importmap-test/scoped-consumer');
    $script = $this->importMapScript();
    $this->assertNotNull($script, 'The import map script is present in the head.');
    $this->assertSame($this->expectedImportMap(), Json::decode($script->getText()));
  }

}
