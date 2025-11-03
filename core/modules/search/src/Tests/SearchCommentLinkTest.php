<?php

/**
 * @file
 * Contains \Drupal\search\Tests\SearchCommentLinkTest.
 */

namespace Drupal\search\Tests;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\RoleInterface;

/**
 * Tests searching comments on operation links on issue #2544516.
 *
 * @group search
 */
class SearchCommentLinkTest extends SearchTestBase {

  use CommentTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('filter', 'node', 'comment');

  /**
   * A user with various administrative permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Profile ID for this test.
   *
   * @var string
   */
  protected $profile = 'standard';

  protected function setUp() {
    parent::setUp();

    // Create and log in an administrative user having access to the Full HTML
    // text format.
    $permissions = array(
      'administer filters',
      'administer permissions',
      'create page content',
      'post comments',
      'skip comment approval',
      'access comments',
    );
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
    $field->setSetting('preview', DRUPAL_OPTIONAL);
    $field->save();

    // Allow anonymous users to search content.
    $edit = array(
      RoleInterface::ANONYMOUS_ID . '[search content]' => 1,
      RoleInterface::ANONYMOUS_ID . '[access comments]' => 1,
      RoleInterface::ANONYMOUS_ID . '[post comments]' => 1,
    );
    $this->drupalPostForm('admin/people/permissions', $edit, t('Save permissions'));

    // Create a node.
    $node = $this->drupalCreateNode(array('type' => 'article'));
    // Post a comment using 'Full HTML' text format.
    $edit_comment = array();
    $edit_comment['subject[0][value]'] = 'Test comment subject';
    $edit_comment['comment_body[0][value]'] = '<h1>' . $comment_body . '</h1>';
    $this->drupalPostForm('comment/reply/node/' . $node->id() .'/comment', $edit_comment, t('Save'));

    // Invoke search index update.
    $this->drupalLogout();
    $this->cronRun();

    // Search for the comment body.
    $edit = array(
      'keys' => "'" . $comment_body . "'",
    );
    $this->drupalPostForm('search/node', $edit, t('Search'));
    $this->assertNoText('Reply', 'Result should not contain operation text like Reply, Login, Delete ...');

  }

}
