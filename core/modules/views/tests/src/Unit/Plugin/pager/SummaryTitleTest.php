<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\pager;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\pager\Full;
use Drupal\views\Plugin\views\pager\Mini;
use Drupal\views\Plugin\views\pager\None;
use Drupal\views\Plugin\views\pager\Some;
use Drupal\views\ViewExecutable;

/**
 * Tests summaryTitle() functions in pager plugins.
 *
 * @group views
 */
class SummaryTitleTest extends UnitTestCase {

  /**
   * A mock display plugin.
   *
   * @var \Drupal\views\Plugin\views\display\DisplayPluginBase
   */
  protected $display;

  /**
   * A mock view.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->view = $this->createMock(ViewExecutable::class);
    $this->display = $this->createMock(DisplayPluginBase::class);
  }

  /**
   * Tests the output of summaryTitle() for the Full, Mini, and Some pagers.
   *
   * @dataProvider providerStandardSummaryTitle
   */
  public function testStandardSummaryTitle(array $options, string $expected): void {
    $classes = [
      Full::class,
      Mini::class,
      Some::class,
    ];
    foreach ($classes as $class) {
      $this->assertSummaryTitle($class, $options, $expected);
    }
  }

  /**
   * Provides test variables for testStandardSummaryTitle().
   */
  public static function providerStandardSummaryTitle(): array {
    return [
      [
        ['items_per_page' => 0, 'offset' => 0],
        'All items',
      ],
      [
        ['items_per_page' => 0, 'offset' => 10],
        'All items except first 10',
      ],
      [
        ['items_per_page' => 1, 'offset' => 0],
        '1 item',
      ],
      [
        ['items_per_page' => 1, 'offset' => 10],
        '1 item, skip 10',
      ],
      [
        ['items_per_page' => 10, 'offset' => 0],
        '10 items',
      ],
      [
        ['items_per_page' => 10, 'offset' => 10],
        '10 items, skip 10',
      ],
    ];
  }

  /**
   * Tests the output of summaryTitle() for the None pager.
   *
   * @dataProvider providerNoneSummaryTitle
   */
  public function testNoneSummaryTitle(array $options, string $expected): void {
    $this->assertSummaryTitle(None::class, $options, $expected);
  }

  /**
   * Provides test variables for testNoneSummaryTitle().
   */
  public static function providerNoneSummaryTitle(): array {
    return [
      [
        ['offset' => 0],
        'All items',
      ],
      [
        ['offset' => 10],
        'Except first 10',
      ],
    ];
  }

  /**
   * Asserts that the output of summaryTitle() matches the expected string.
   *
   * @param string $class
   *   A pager plugin class name.
   * @param array $options
   *   Options for initializing the pager plugin.
   * @param string $expected
   *   The expected output.
   */
  protected function assertSummaryTitle(string $class, array $options, string $expected): void {
    /** @var \Drupal\views\Plugin\views\pager\PagerPluginBase $plugin */
    $plugin = $this->getMockBuilder($class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();

    $plugin->init($this->view, $this->display, $options);
    $this->assertEquals($expected, $plugin->summaryTitle()->render());
  }

}
