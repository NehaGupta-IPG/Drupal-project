<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that views exposed form works with a dynamic contextual argument.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class ViewsExposedFormTest extends ViewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'user',
    'node',
    'block',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static array $testViews = ['test_views_query_parameter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['views_test_config']): void {
    parent::setUp($import_test_views, $modules);

    // Create Article node type.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalLogin($this->drupalCreateUser());
  }

  /**
   * Tests that when "raw value from url" is set in contextual filter, it works.
   */
  public function testExposedFormInBlock(): void {
    $this->drupalPlaceBlock('views_block:test_views_query_parameter-block_1', ['label' => 'Filter Block']);
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

}
