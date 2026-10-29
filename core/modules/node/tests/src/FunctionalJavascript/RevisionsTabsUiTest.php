<?php

declare(strict_types=1);

namespace Drupal\Tests\node\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\filter\FilterFormatRepositoryInterface;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Runs tests on Revisions UI using Stark.
 */
#[Group('node')]
#[RunTestsInSeparateProcesses]
class RevisionsTabsUiTest extends WebDriverTestBase {

  /**
   * An array of node revisions.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
      'display_submitted' => FALSE,
    ]);

    // Create initial node.
    $node = $this->drupalCreateNode();

    $nodes = [];

    // Get original node.
    $nodes[] = clone $node;

    // Create two revisions.
    $revision_count = 2;
    for ($i = 0; $i < $revision_count; $i++) {

      // Create revision with a random title and body and update variables.
      $node->title = $this->randomMachineName();
      $node->body = [
        'value' => $this->randomMachineName(32),
        'format' => \Drupal::service(FilterFormatRepositoryInterface::class)->getDefaultFormat()->id(),
      ];
      $node->setNewRevision();

      $node->save();

      // Make sure we get revision information.
      $node = Node::load($node->id());
      $nodes[] = clone $node;
    }

    $this->nodes = $nodes;

    // Create the test user and log in.
    $admin_user = $this->drupalCreateUser([
      'access administration pages',
      'view the administration theme',
      'administer nodes',
      'edit any page content',
      'view page revisions',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests Revisions UI displays local tasks tabs.
   */
  public function testRevisionsUiTabsExist(): void {
    $this->drupalGet('node/' . $this->nodes[0]->id() . '/revisions/1/view');
    $assert_session = $this->assertSession();

    // The 'Revisions' active primary tab.
    $assert_session->elementExists('css', 'a.tabs__link.js-tabs-link.is-active');

    // The 'View', 'Edit' and 'Delete' primary tabs.
    $assert_session->elementExists('css', 'li.tabs__tab:nth-child(1) > a:nth-child(1)');
    $assert_session->elementExists('css', 'li.tabs__tab:nth-child(2) > a:nth-child(1)');
    $assert_session->elementExists('css', 'li.tabs__tab:nth-child(3) > a:nth-child(1)');
  }

}
