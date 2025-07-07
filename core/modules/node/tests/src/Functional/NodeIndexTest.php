<?php

declare(strict_types=1);

namespace Drupal\Tests\automated_cron\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Test that a node is indexed immediately after create / update / delete.
 *
 * @group automated_cron
 */
class NodeIndexTest extends BrowserTestBase {
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automated_cron', 'search', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Data used for node creation.
   *
   * @var array
   */
  private array $edit = [];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page']);

    \Drupal::configFactory()->getEditable('automated_cron.settings')
      ->set('max_items_to_process', 1)
      ->set('max_concurrent_queue_process', 1)
      ->save();

    $web_user = $this->drupalCreateUser([
      'search content',
      'create page content',
      'edit any page content',
      'delete any page content',
    ]);

    $this->drupalLogin($web_user);

    // Create a node.
    $this->edit['title[0][value]'] = $this->randomMachineName(8);
    $this->edit['body[0][value]'] = $this->randomMachineName(16);
    $this->drupalGet('node/add/page');
    $this->submitForm($this->edit, 'Save');
  }

  /**
   * Test that the node is index immediately after creation.
   */
  public function testNodeCreated(): void {
    sleep(1);
    $this->drupalGet('search/node', ['query' => ['keys' => urlencode($this->edit['title[0][value]'])]]);
    $this->assertSession()->pageTextNotContains('Your search yielded no results.');
    $this->assertSession()->linkExists($this->edit['title[0][value]']);
  }

  /**
   * Test that the node is index immediately after update.
   */
  public function testNodeUpdated(): void {
    $title_key = 'title[0][value]';

    $this->drupalGet('node/1/edit');
    $edit = [$title_key => $this->randomMachineName(8)];
    $this->submitForm($edit, 'Save');
    sleep(1);
    // Test that old content is removed from index.
    $this->drupalGet('search/node', ['query' => ['keys' => urlencode($this->edit[$title_key])]]);
    $this->assertSession()->linkNotExists($this->edit[$title_key]);
    // Test that new content is available in index
    $this->drupalGet('search/node', ['query' => ['keys' => urlencode($edit[$title_key])]]);
    $this->assertSession()->pageTextNotContains('Your search yielded no results.');
    $this->assertSession()->linkExists($edit[$title_key]);
  }

}
