<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\node\Entity\Node;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the core Drupal\views\Plugin\views\argument\StringArgument handler.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class ArgumentStringTest extends ViewsKernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $testViews = [
    'test_glossary',
    'test_argument_string_exclude',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'text',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['field', 'node']);

    $node_type = $this->createContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);

    // Setup some nodes, one with a, two with b and three with c.
    $counter = 1;
    foreach (['a', 'b', 'c'] as $char) {
      for ($i = 0; $i < $counter; $i++) {
        Node::create([
          'type' => $node_type->id(),
          'title' => $char . $this->randomMachineName(),
          'body' => [
            'value' => $char,
          ],
        ])->save();
      }
    }
  }

  /**
   * Tests the glossary feature.
   */
  public function testGlossary(): void {
    $view = Views::getView('test_glossary');
    $this->executeView($view);

    $count_field = 'nid';
    foreach ($view->result as &$row) {
      if (str_starts_with($view->field['title']->getValue($row), 'a')) {
        $this->assertEquals(1, $row->{$count_field});
      }
      if (str_starts_with($view->field['title']->getValue($row), 'b')) {
        $this->assertEquals(2, $row->{$count_field});
      }
      if (str_starts_with($view->field['title']->getValue($row), 'c')) {
        $this->assertEquals(3, $row->{$count_field});
      }
    }
  }

  /**
   * Test exclude value filter.
   */
  public function testExclude(): void {
    $body_contents = ['a', 'b', 'c'];
    foreach ($body_contents as $char) {
      // View needs to be reconstructed every time to force results
      // to reload with new arguments.
      $view = Views::getView('test_argument_string_exclude');
      $this->executeView($view, [$char]);
      $body_results = array_map(
        fn ($row) => $view->field['body']->getValue($row),
        $view->result
      );
      $expected_results = array_values(
        array_diff($body_contents, [$char])
      );
      $this->assertSame($expected_results, $body_results);
    }
  }

}
