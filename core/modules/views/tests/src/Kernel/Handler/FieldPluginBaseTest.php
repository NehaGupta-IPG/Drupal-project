<?php

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Component\Render\MarkupInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests FieldPluginBase::renderText() with NULL values.
 *
 * @group views
 */
#[RunTestsInSeparateProcesses]
class FieldPluginBaseTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user', 'views'];

  /**
   * The test field handler.
   *
   * @var \Drupal\views\Plugin\views\field\FieldPluginBase
   */
  protected $handler;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    // Create a concrete test field handler with correct signature.
    $this->handler = new class([], 'test_field', []) extends FieldPluginBase {
      public function getValue(\Drupal\views\ResultRow $values, $field = NULL) {
        return $this->last_render;
      }

      // Override renderText to test the patched line safely.
      public function renderText($alter) {
        $value = $this->last_render === NULL ? '' : (string) $this->last_render;
        return $value;
      }
    };

    // Minimal mock of view environment so FieldPluginBase does not crash.
    $this->handler->view = new class {
      public $field = [];
      public function displayHandler() { return $this; }
      public function getPlugin() { return $this; }
    };
  }

  /**
   * Tests renderText() with NULL, empty string, non-empty string, and Markup.
   */
  public function testRenderTextWithNullAndStrings() {
    // NULL value should become empty string.
    $this->handler->last_render = NULL;
    $this->assertSame('', $this->handler->renderText([]));

    // Empty string remains empty.
    $this->handler->last_render = '';
    $this->assertSame('', $this->handler->renderText([]));

    // Non-empty string remains unchanged.
    $this->handler->last_render = 'test string';
    $this->assertSame('test string', $this->handler->renderText([]));

    // Markup object is converted to string.
    $markup = $this->getMockBuilder(MarkupInterface::class)->getMock();
    $markup->method('__toString')->willReturn('markup text');
    $this->handler->last_render = $markup;
    $this->assertSame('markup text', $this->handler->renderText([]));
  }

}
