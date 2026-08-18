<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\views\filter;

use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\BooleanOperator;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\views\Plugin\views\filter\BooleanOperator.
 */
#[CoversClass(BooleanOperator::class)]
#[Group('Views')]
class BooleanOperatorTest extends UnitTestCase {

  /**
   * Tests that accept_null value is properly updated.
   */
  #[DataProvider('provideAcceptNullInit')]
  public function testAcceptNullInit(array $options, bool $expected): void {
    $plugin_definition = ['title' => $this->randomMachineName()];

    $plugin = new BooleanOperator([], 'boolean', $plugin_definition);
    $plugin->setStringTranslation($this->getStringTranslationStub());

    $view = $this->prophesize(ViewExecutable::class)->reveal();
    $display = $this->prophesize(DisplayPluginBase::class)->reveal();
    $view->display_handler = $display;

    $plugin->init($view, $view->display_handler, $options);
    self::assertSame($expected, $plugin->accept_null);
  }

  /**
   * Data provider for testAcceptNullInit().
   */
  public static function provideAcceptNullInit(): array {
    return [
      'default' => [
        'options' => [],
        'expected' => FALSE,
      ],
      'accept_null' => [
        'options' => ['accept_null' => TRUE],
        'expected' => TRUE,
      ],
      'accept null' => [
        'options' => ['accept null' => TRUE],
        'expected' => TRUE,
      ],
    ];
  }

}
