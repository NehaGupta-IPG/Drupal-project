<?php

declare(strict_types=1);

namespace Drupal\Tests\filter\Unit;

use Drupal\filter\Plugin\Filter\FilterAutoP;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\filter\Plugin\Filter\FilterAutoP
 * @group filter
 */
class FilterAutoPTest extends UnitTestCase {

  /**
   * The filter to test.
   *
   * @var \Drupal\filter\Plugin\Filter\FilterAutoP
   */
  protected $filter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->filter = new FilterAutoP([], 'filter_autop', ['provider' => 'test']);
    $path = __DIR__ . '/../../fixtures/autop';
    require_once($path . '/drupal-10.filter-_filter_autop.php');
  }

  /**
   * @covers ::process
   *
   * @dataProvider providerFilterMarkup
   *
   * @param string $html
   *   Input HTML.
   * @param string $expected
   *   The expected output string.
   */
  public function testProcess($html, $expected): void {
    $result = $this->filter->process($html, 'en');
    $this->assertSame($expected, $result->getProcessedText());
  }

  /**
   * Provides data for testProcess.
   *
   * @return array
   *   An array of test data.
   */
  public static function providerFilterMarkup(): array {
    $path = __DIR__ . '/../../fixtures/autop';
    return [
      [
        file_get_contents($path . '/filter.autop-input-1.txt'),
        file_get_contents($path . '/filter.autop-output-1.txt'),
      ],
      [
        file_get_contents($path . '/filter.autop-input-2.txt'),
        file_get_contents($path . '/filter.autop-output-2.txt'),
      ],
      [
        file_get_contents($path . '/filter.autop-input-3.txt'),
        file_get_contents($path . '/filter.autop-output-3.txt'),
      ],
      [
        file_get_contents($path . '/filter.autop-input-4.txt'),
        file_get_contents($path . '/filter.autop-output-4.txt'),
      ]
    ];
  }

}
