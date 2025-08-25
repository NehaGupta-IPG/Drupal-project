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
    $this->assertSame($expected, $result);
  }

  /**
   * Provides data for testProcess.
   *
   * @return array
   *   An array of test data.
   */
  public static function providerFilterMarkup(): array {
    return [
      [
        'Single row.',
        '<p>Single row.</p>'
      ],
      [
        'First row of text.' . PHP_EOL . 'Second row of text.',
        '<p>First row of text.<br />' . PHP_EOL . 'Second row of text.</p>'
      ],
      [
        'First row of text.' . str_repeat(PHP_EOL, 2) . 'Second row of text.',
        '<p>First row of text.</p>' . PHP_EOL . '<p>Second row of text.</p>'
      ],
      [
        '<ul><li>First list item.</li>' . PHP_EOL . '<li>Second list item contains' . PHP_EOL . 'a single line break.' . str_repeat(PHP_EOL, 2) . 'And then double line breaks.</li></ul>',
        '<ul><li>First list item.</li>' . PHP_EOL . '<li>Second list item contains<br />' . PHP_EOL . 'a single line break.' . PHP_EOL . '<p>And then double line breaks.</p></li></ul>'],
    ];
  }

}
