<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Core\Block;

use Behat\Mink\Element\NodeElement;
use Drupal\Tests\BrowserTestBase;

/**
 * @coversDefaultClass \Drupal\Core\Block\Plugin\Block\PageTitleBlock
 *
 * @group block
 */
class PageTitleBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['page_title_block_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * @covers ::build
   * @dataProvider providerTestBuild
   *
   * @param array $expected
   *   The expected page titles, in order.
   * @param string $set_title
   *   The value to call ::setTitle() with.
   * @param string $render_array_title
   *   The value for #title in the render array.
   */
  public function testBuild($expected, $set_title, $render_array_title): void {
    \Drupal::state()->set('page_title_block_test.set_title', $set_title);
    \Drupal::state()->set('page_title_block_test.render_array_title', $render_array_title);

    $this->drupalGet('page-title-block-test-page');
    $page_titles = array_map(function (NodeElement $element) {
      return $element->getText();
    }, $this->getSession()->getPage()->findAll('css', '.page-title'));
    $this->assertSame($expected, $page_titles);
  }

  /**
   * Provides test data for ::testBuild().
   */
  public static function providerTestBuild(): array {
    // Data provider values are:
    // - the expected page titles, in order
    // - the value to call ::setTitle() with
    // - the value for #title in the render array.
    $data = [];
    $data['no title manipulation'] = [
      ['Test page'],
      NULL,
      NULL,
    ];
    $data['provide a top-level #title'] = [
      ['Test page'],
      NULL,
      'render_array_title',
    ];
    $data['call setTitle() on the block'] = [
      ['Test page'],
      'set_title',
      NULL,
    ];
    $data['call setTitle() on the block and provide a top-level #title'] = [
      ['Test page'],
      'set_title',
      'render_array_title',
    ];
    return $data;
  }

}
