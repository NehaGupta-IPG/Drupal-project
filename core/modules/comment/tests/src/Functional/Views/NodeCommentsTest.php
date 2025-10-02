<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Functional\Views;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests comments on nodes.
 */
#[Group('comment')]
#[RunTestsInSeparateProcesses]
class NodeCommentsTest extends CommentTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'test_comment_count',
    'test_comment_last_updated_sort',
    'test_comment_last_updated_click_sort',
  ];

  /**
   * Test the comment count field.
   */
  public function testCommentCount(): void {
    $this->drupalGet('test-comment-count');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCount(2, $this->cssSelect('.views-row'));
    $comment_count_with_comment = $this->cssSelect(".views-field-comment-count span:contains('1')");
    $this->assertCount(1, $comment_count_with_comment);
    $comment_count_without_comment = $this->cssSelect(".views-field-comment-count span:contains('0')");
    $this->assertCount(1, $comment_count_without_comment);

    // Create a content type with no comment field, and add a node.
    $this->drupalCreateContentType(['type' => 'no_comment', 'name' => 'No comment page']);
    $this->nodeUserPosted = $this->drupalCreateNode(['type' => 'no_comment']);
    $this->drupalGet('test-comment-count');

    // Test that the node with no comment field is also shown.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCount(3, $this->cssSelect('.views-row'));
    $comment_count_with_comment = $this->cssSelect(".views-field-comment-count span:contains('1')");
    $this->assertCount(1, $comment_count_with_comment);
    $comment_count_without_comment = $this->cssSelect(".views-field-comment-count span:contains('0')");
    $this->assertCount(2, $comment_count_without_comment);
  }

  /**
   * Test comment statistics sort with nodes without comments.
   *
   * Tests that sorting by comment statistics (last updated/commented) works
   * correctly for nodes without comments by falling back to the node's
   * changed timestamp when no comment statistics exist.
   */
  public function testCommentStatisticsSort(): void {
    // Create a content type without comments.
    $this->drupalCreateContentType([
      'type' => 'no_comments',
      'name' => 'No Comments Type',
    ]);

    // Create nodes with specific timestamps to test sort order.
    $request_time = \Drupal::time()->getRequestTime();

    // Node with comments (older changed time).
    // This type has comments enabled from setUp() and created an hour ago.
    $node_with_comments = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Node with comments',
      'created' => $request_time - 3600,
      'changed' => $request_time - 3600,
    ]);

    // Node without comments (newer changed time).
    // This type has no comment field and created 30 minutes ago.
    $node_without_comments = $this->drupalCreateNode([
      'type' => 'no_comments',
      'title' => 'Node without comments',
      'created' => $request_time - 1800,
      'changed' => $request_time - 1800,
    ]);

    // Test the ViewsSort plugin behavior.
    $this->drupalGet('test-comment-last-updated-sort');
    $this->assertSession()->statusCodeEquals(200);

    // Get all rows to check sort order.
    $rows = $this->cssSelect('.views-row');
    $this->assertGreaterThanOrEqual(2, count($rows), 'Both nodes should be displayed in the view.');

    // Extract node IDs from the rows to check order.
    $displayed_nids = [];
    foreach ($rows as $row) {
      $nid_field = $row->find('css', '.views-field-nid');
      if ($nid_field) {
        $displayed_nids[] = (int) trim($nid_field->getText());
      }
    }

    // Expected correct sort order (DESC): nodes should sort by their actual
    // timestamps. The node without comments should sort first because it has
    // a newer changed time and should fall back to that timestamp.
    $expected_correct_order = [
      $node_without_comments->id(),
      $node_with_comments->id(),
    ];

    $this->assertEquals($expected_correct_order, array_slice($displayed_nids, 0, 2),
      'Nodes should sort correctly by timestamp, with nodes without comments falling back to their changed time.');
  }

  /**
   * Test comment statistics click sort with nodes without comments.
   *
   * Tests that click sorting by comment statistics (last updated/commented)
   * field works correctly for nodes without comments by falling back to
   * the node's changed timestamp when no comment statistics exist.
   */
  public function testCommentStatisticsClickSort(): void {
    // Create a content type without comments.
    $this->drupalCreateContentType([
      'type' => 'no_comments',
      'name' => 'No Comments Type',
    ]);

    // Create nodes with specific timestamps to test click sort behavior.
    $request_time = \Drupal::time()->getRequestTime();

    // Node with comments (older changed time).
    // This type has comments enabled from setUp() and created an hour ago.
    $node_with_comments = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Node with comments',
      'created' => $request_time - 3600,
      'changed' => $request_time - 3600,
    ]);

    // Node without comments (newer changed time).
    // This type has no comment field and created 30 minutes ago.
    $node_without_comments = $this->drupalCreateNode([
      'type' => 'no_comments',
      'title' => 'Node without comments',
      'created' => $request_time - 1800,
      'changed' => $request_time - 1800,
    ]);

    // Test the ViewsField plugin click sort behavior.
    $this->drupalGet('test-comment-last-updated-click-sort');
    $this->assertSession()->statusCodeEquals(200);

    // Verify the table has the sortable column.
    $this->assertSession()->elementExists('css', 'th.views-field-comment-entity-statistics-last-updated');

    // Get all rows to verify default DESC sort order.
    $rows = $this->cssSelect('.views-row');
    $this->assertGreaterThanOrEqual(2, count($rows), 'Both nodes should be displayed in the view.');

    // Extract node IDs to verify sort order with default DESC sort.
    $displayed_nids_desc = [];
    foreach ($rows as $row) {
      $nid_field = $row->find('css', '.views-field-nid');
      if ($nid_field) {
        $displayed_nids_desc[] = (int) trim($nid_field->getText());
      }
    }

    // Expected correct sort order (DESC): newer timestamps first.
    // The node without comments should sort first because it has a newer
    // changed time and should fall back to that timestamp.
    $expected_desc_order = [
      $node_without_comments->id(),
      $node_with_comments->id(),
    ];

    $this->assertEquals($expected_desc_order, array_slice($displayed_nids_desc, 0, 2),
      'Default DESC sort: nodes should sort correctly by timestamp with fallback to changed time.');

    // Test ascending sort by clicking the column header.
    $sort_link = $this->getSession()->getPage()->find('css', 'th.views-field-comment-entity-statistics-last-updated a');
    $this->assertNotNull($sort_link, 'Sort link should exist for the last updated column.');
    $sort_link->click();

    // After clicking, get the new sort order.
    $rows_asc = $this->cssSelect('.views-row');
    $displayed_nids_asc = [];
    foreach ($rows_asc as $row) {
      $nid_field = $row->find('css', '.views-field-nid');
      if ($nid_field) {
        $displayed_nids_asc[] = (int) trim($nid_field->getText());
      }
    }

    // Expected correct sort order (ASC): older timestamps first.
    $expected_asc_order = [
      $node_with_comments->id(),
      $node_without_comments->id(),
    ];

    $this->assertEquals($expected_asc_order, array_slice($displayed_nids_asc, 0, 2),
      'ASC sort: nodes should sort correctly by timestamp with fallback to changed time.');

    // Verify that the sort order actually changed.
    $this->assertNotEquals($displayed_nids_desc, $displayed_nids_asc,
      'Click sort should reverse the sort order.');
  }

}
