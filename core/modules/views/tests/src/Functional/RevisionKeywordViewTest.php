<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Handler;

use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests the revision keyword exposed filter in views.
 *
 * @group views
 */
class RevisionKeywordViewTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_revisions_search'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'search',
    'views',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['views_test_config']): void {
    parent::setUp($import_test_views, $modules);

    $this->enableViewsTestModule();
    $admin_user = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($admin_user);

    // Create content type.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    // Create initial revision with title 'alpha title'.
    $node = Node::create([
      'type' => 'page',
      'title' => 'alpha title',
    ]);
    $node->save();

    // Create second revision with title 'beta title'.
    $node->setNewRevision(TRUE);
    $node->setTitle('alpha2 title');
    $node->isDefaultRevision(TRUE);
    $node->save();
  }

  /**
   * Tests filtering by revision title.
   */
  public function testKeywordSearchFilterWorks(): void {
    // Alpha revision should be found when filtered.
    $this->drupalGet('/test-revisions', ['query' => ['keys' => 'alpha2']]);
    $this->assertSession()->statusCodeEquals(200);

    // Beta revision should be found when filtered.
    $this->drupalGet('/test-revisions', ['query' => ['keys' => 'beta']]);
    $this->assertSession()->statusCodeEquals(200);
  }

}
