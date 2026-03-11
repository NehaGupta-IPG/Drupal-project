<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\FunctionalJavascript;

use Drupal\comment\CommentingStatus;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the comment widget summary text via JavaScript.
 */
#[Group('comment')]
class CommentWidgetSummaryTest extends WebDriverTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'comment',
    'node',
    'field_ui',
    'datetime',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $types = NodeType::loadMultiple();
    if (empty($types['article'])) {
      $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    }

    // Create comment field on article.
    $this->addDefaultCommentField('node', 'article');

    // Create a test node.
    $this->node = $this->drupalCreateNode([
      'type' => 'article',
      'promote' => 1,
    ]);

    $this->drupalPlaceBlock('local_tasks_block');
  }

  /**
   * Tests the comment widget summary text is shown correctly.
   */
  public function testCommentWidgetSummaryText(): void {
    $admin = $this->drupalCreateUser([
      'administer nodes',
      'administer comments',
      'edit any article content',
      'access content',
    ]);
    $this->drupalLogin($admin);

    // Visit the node edit page.
    $this->drupalGet('node/' . $this->node->id() . '/edit');

    // Wait for JS to set the summary text and assert it shows 'Open'.
    $summary = $this->assertSession()->waitForElement('css', 'details.comment-node-settings-form > summary');
    $this->assertStringContainsString('Open', $summary->getText());

    // Change status to Closed and verify summary updates.
    $this->node->set('comment', CommentingStatus::Closed->value);
    $this->node->save();
    $this->drupalGet('node/' . $this->node->id() . '/edit');

    $summary = $this->assertSession()->waitForElement('css', 'details.comment-node-settings-form > summary');
    $this->assertStringContainsString('Closed', $summary->getText());

    // Change status to Hidden and verify summary updates.
    $this->node->set('comment', CommentingStatus::Hidden->value);
    $this->node->save();
    $this->drupalGet('node/' . $this->node->id() . '/edit');

    $summary = $this->assertSession()->waitForElement('css', 'details.comment-node-settings-form > summary');
    $this->assertStringContainsString('Hidden', $summary->getText());
  }

}
