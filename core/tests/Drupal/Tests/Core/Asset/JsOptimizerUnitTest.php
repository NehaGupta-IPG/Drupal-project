<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Asset;

use Drupal\Core\Asset\JsOptimizer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests the JS asset optimizer.
 */
#[Group('Asset')]
class JsOptimizerUnitTest extends UnitTestCase {

  /**
   * A JS asset optimizer.
   *
   * @var \Drupal\Core\Asset\JsOptimizer
   */
  protected $optimizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->optimizer = new JsOptimizer($this->createStub(LoggerInterface::class));
  }

  /**
   * Provides data for the JS asset cleaning test.
   *
   * @see \Drupal\Core\Asset\JsOptimizer::clean()
   *
   * @return array
   *   An array of test data.
   */
  public static function providerTestClean(): array {
    $path = dirname(__FILE__) . '/js_test_files/';
    return [
      // File. Tests:
      // - Stripped sourceMappingURL with comment # syntax.
      0 => [
        file_get_contents($path . 'source_mapping_url.min.js'),
        file_get_contents($path . 'source_mapping_url.min.js.optimized.js'),
      ],
      // File. Tests:
      // - Stripped sourceMappingURL with comment @ syntax.
      1 => [
        file_get_contents($path . 'source_mapping_url_old.min.js'),
        file_get_contents($path . 'source_mapping_url_old.min.js.optimized.js'),
      ],
      // File. Tests:
      // - Stripped sourceURL with comment # syntax.
      2 => [
        file_get_contents($path . 'source_url.min.js'),
        file_get_contents($path . 'source_url.min.js.optimized.js'),
      ],
      // File. Tests:
      // - Stripped sourceURL with comment @ syntax.
      3 => [
        file_get_contents($path . 'source_url_old.min.js'),
        file_get_contents($path . 'source_url_old.min.js.optimized.js'),
      ],
    ];
  }

  /**
   * Tests cleaning of a JS asset group containing 'type' => 'file'.
   */
  #[DataProvider('providerTestClean')]
  public function testClean(string|bool $js_asset, string|bool $expected): void {
    $this->assertEquals($expected, $this->optimizer->clean($js_asset));
  }

  /**
   * Tests that the javascript may be cleaned without backtracking.
   */
  public function testCleanWithRecursionLimit() {
    // Specify a hard backtracking limit.
    ini_set('pcre.backtrack_limit', 100);
    $backtrack_limit = (int) ini_get('pcre.backtrack_limit');
    $this->assertEquals(100, $backtrack_limit);
    $script = <<<JS
(function($) { "use strict"; })
//# sourceMappingURL=data:application/json;charset=utf-8;base64,
JS;
    // Generate a source map URL that would exceed the backtrack limit.
    $script .= str_repeat('x', $backtrack_limit);
    // Add an empty space after the sourcemap, followed by other
    // miscellaneous code.
    $script .= <<<JS
 // I appear after the sourcemap URL.
(function($) { "use strict"; console.log('Hello'); })
JS;
    $expected_script = <<<JS
(function($) { "use strict"; })
// I appear after the sourcemap URL.
(function($) { "use strict"; console.log('Hello'); })
JS;

    $this->assertEquals($expected_script, $this->optimizer->clean($script));
  }

  /**
   * Provides data for the JS asset optimize test.
   *
   * @see \Drupal\Core\Asset\JsOptimizer::optimize()
   *
   * @return array
   *   An array of test data.
   */
  public static function providerTestOptimize(): array {
    $path = dirname(__FILE__) . '/js_test_files/';
    return [
      0 => [
        [
          'type' => 'file',
          'preprocess' => TRUE,
          'data' => $path . 'utf8_bom.js',
        ],
        file_get_contents($path . 'utf8_bom.js.optimized.js'),
      ],
      1 => [
        [
          'type' => 'file',
          'preprocess' => TRUE,
          'data' => $path . 'utf16_bom.js',
        ],
        file_get_contents($path . 'utf16_bom.js.optimized.js'),
      ],
      2 => [
        [
          'type' => 'file',
          'preprocess' => TRUE,
          'data' => $path . 'latin_9.js',
          'attributes' => ['charset' => 'ISO-8859-15'],
        ],
        file_get_contents($path . 'latin_9.js.optimized.js'),
      ],
      3 => [
        [
          'type' => 'file',
          'preprocess' => TRUE,
          'data' => $path . 'to_be_minified.js',
        ],
        file_get_contents($path . 'to_be_minified.js.optimized.js'),
      ],
      4 => [
        [
          'type' => 'file',
          'preprocess' => TRUE,
          'data' => $path . 'syntax_error.js',
        ],
        // When there is a syntax error, the 'optimized' contents are the
        // contents of the original file.
        file_get_contents($path . 'syntax_error.js'),
      ],
    ];
  }

  /**
   * Tests cleaning of a JS asset group containing 'type' => 'file'.
   */
  #[DataProvider('providerTestOptimize')]
  public function testOptimize(array $js_asset, string|bool $expected): void {
    $this->assertEquals($expected, $this->optimizer->optimize($js_asset));
  }

}
