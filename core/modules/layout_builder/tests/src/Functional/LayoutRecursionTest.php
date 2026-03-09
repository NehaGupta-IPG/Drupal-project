<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;

/**
 * Tests recursion prevention in layouts.
 *
 * @group layout_builder
 */
class LayoutRecursionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'layout_builder',
    'block',
    'node',
    'layout_builder_recursive_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    for ($i = 1; $i <= 9; $i++) {
      Node::create([
        'type' => 'type_with_recursion',
        'title' => "Title $i",
        'body' => "Body text $i",
      ])->save();
    }

    LayoutBuilderEntityViewDisplay::load('node.type_with_recursion.default')
      ->enableLayoutBuilder()
      ->save();
  }

  /**
   * Tests recursion prevention.
   */
  public function testRecursionPrevention() {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
    ]));

    $this->drupalGet('node/1');

    // If recursion prevention is not properly implemented for Views within
    // Layout Builder layouts, there will be an error page and the actual
    // contents of node 1 will never be rendered. A check for anything that
    // should appear in node 1 proves recursion protection was successful.
    $assert_session->pageTextContains('List all nodes');
  }

}
