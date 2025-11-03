<?php

/**
 * @file
 * Contains \Drupal\search\Tests\SearchCommentLinkTest.
 */

namespace Drupal\search\Tests;

use Drupal\comment\CommentPreviewMode;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\user\RoleInterface;

/**
 * Tests searching comments on operation links on issue #2544516.
 */
#[Group('search')]
#[RunTestsInSeparateProcesses]
class SearchCommentLinkTest extends BrowserTestBase {

  use CommentTestTrait;
  use CronRunTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['filter', 'node', 'comment'];

  /**
   * A user with various administrative permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  protected function setUp(): void {
    parent::setUp();

    // Create and log in an administrative user having access to the Full HTML
    // text format.
    $permissions = [
      'administer filters',
      'administer permissions',
      'create page content',
      'post comments',
      'skip comment approval',
      'access comments',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->adminUser);
    // Add a comment field.
    $this->addDefaultCommentField('node', 'article');
  }

  /**
   * Verify that comments are rendered using proper format in search results.
   */
  function testSearchResultsComment() {
    $node_storage = $this->container->get('entity.manager')->getStorage('node');
    // Create basic_html format that escapes all HTML.

    $comment_body = 'Test comment body';

    // Make preview optional.
    $field = FieldConfig::loadByName('node', 'article', 'comment');
    $field->setSetting('preview', CommentPreviewMode::Optional);
    $field->save();

    // Allow anonymous users to search content.
    $edit = [
      RoleInterface::ANONYMOUS_ID . '[search content]' => 1,
      RoleInterface::ANONYMOUS_ID . '[access comments]' => 1,
      RoleInterface::ANONYMOUS_ID . '[post comments]' => 1,
    ];
    $this->drupalGet('admin/people/permissions');
    $this->submitForm($edit, 'Save permissions');

    // Create a node.
    $node = $this->drupalCreateNode(['type' => 'article']);
    // Post a comment using 'Full HTML' text format.
    $edit_comment = [];
    $edit_comment['subject[0][value]'] = 'Test comment subject';
    $edit_comment['comment_body[0][value]'] = '<h1>' . $comment_body . '</h1>';
    $this->drupalGet('comment/reply/node/' . $node->id() . '/comment');
    $this->submitForm($edit_comment, 'Save');

    // Invoke search index update.
    $this->drupalLogout();
    $this->cronRun();

    // Search for the comment body.
    $edit = [
      'keys' => "'" . $comment_body . "'",
    ];
    $this->drupalGet('search/node');
    $this->submitForm($edit, 'Search');
    $this->assertSession()->pageTextNotContains('Result should not contain operation text like Reply, Login, Delete ...');

  }

}
