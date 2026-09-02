<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Asset;

use Behat\Mink\Element\NodeElement;
use Drupal\Component\Serialization\Json;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the import map of a theme that overrides a library's importmap.
 *
 * The importmap_test_theme theme overrides the 'importmap' library of the
 * importmap_test module in libraries-override, replacing the source of the
 * 'importmap-test-foo' module specifier with a theme file, and adding
 * integrity metadata for that file.
 */
#[Group('asset')]
#[RunTestsInSeparateProcesses]
class ImportMapOverrideTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'importmap_test_theme';

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
   * Returns the theme path of the importmap_test_theme theme.
   *
   * @return string
   *   The theme path.
   */
  protected function themePath(): string {
    return $this->container->get('extension.list.theme')->getPath('importmap_test_theme');
  }

  /**
   * Returns the expected import map, as emitted in the HTML head.
   *
   * The 'importmap-test-foo' entry is the theme file, as specified by the
   * theme's libraries-override, and the integrity section includes the
   * integrity metadata of the theme file, added by the same override.
   *
   * @return array
   *   The import map JSON, decoded.
   */
  protected function expectedImportMap(): array {
    $path = $this->modulePath();
    $theme_path = $this->themePath();
    $theme_foo = $this->fileUrlGenerator->generate("$theme_path/js/theme-foo.js")->toString() . '?v=1.0';
    return [
      'imports' => [
        'importmap-test-foo' => $theme_foo,
        'importmap-test-prefix/' => $this->fileUrlGenerator->generate("$path/js/prefix/")->toString() . '?v=1.0',
        'importmap-test-external' => 'https://example.com/importmap-test-external.mjs',
      ],
      'scopes' => [
        $this->fileUrlGenerator->generate("$path/js/scope/")->toString() => [
          'importmap-test-foo' => $this->fileUrlGenerator->generate("$path/js/scope/foo.js")->toString() . '?v=1.0',
        ],
      ],
      'integrity' => [
        $this->fileUrlGenerator->generate("$path/js/foo.js")->toString() . '?v=1.0' => 'sha384-oXiGjHO+NOxR1XH8somfajtqMWa5PTleNh5VGncEbQQTrZZboU8xIMhoDzGBbroU',
        $theme_foo => 'sha384-theme',
      ],
    ];
  }

  /**
   * Returns the import map script element from the page.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The import map script element, or NULL if it is not present.
   */
  protected function importMapScript(): ?NodeElement {
    $elements = $this->getSession()->getPage()->findAll('css', 'script[type="importmap"]');
    return $elements ? $elements[0] : NULL;
  }

  /**
   * Tests that the theme override is reflected in the emitted import map.
   */
  public function testImportMapInHead(): void {
    $this->drupalGet('<front>');
    $script = $this->importMapScript();
    $this->assertNotNull($script, 'The import map script is present in the head.');
    $this->assertSame($this->expectedImportMap(), Json::decode($script->getText()));
  }

  /**
   * Tests that the module preloads of attached libraries use the override.
   */
  public function testImportMapPreloads(): void {
    $this->drupalGet('/importmap-test/consumer');
    $script = $this->importMapScript();
    $this->assertNotNull($script, 'The import map script is present in the head.');
    $this->assertSame($this->expectedImportMap(), Json::decode($script->getText()));
    $path = $this->modulePath();
    $theme_path = $this->themePath();
    $expected_preloads = [
      $this->fileUrlGenerator->generate("$theme_path/js/theme-foo.js")->toString() . '?v=1.0',
      'https://example.com/importmap-test-external.mjs',
      $this->fileUrlGenerator->generate("$path/js/scope/foo.js")->toString() . '?v=1.0',
    ];
    $preloads = $this->getSession()->getPage()->findAll('css', 'link[rel="modulepreload"]');
    $this->assertCount(count($expected_preloads), $preloads);
    foreach ($preloads as $index => $preload) {
      $this->assertSame($expected_preloads[$index], $preload->getAttribute('href'));
    }
  }

}
