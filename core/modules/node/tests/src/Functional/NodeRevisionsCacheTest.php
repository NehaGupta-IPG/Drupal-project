<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\Tests\system\Functional\Cache\PageCacheTagsTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Create a node with revisions and test cache invalidation during CRUD operations.
 */
#[Group('node')]
#[RunTestsInSeparateProcesses]
class NodeRevisionsCacheTest extends PageCacheTagsTestBase {

  use StringTranslationTrait;

  /**
   * The user that with administration access on revisions.
   */
  protected UserInterface $webUser;

  /**
   * The initial node.
   */
  protected NodeInterface $node;

  /**
   * The node revision.
   */
  protected NodeInterface $revision;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'datetime'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    // Create and log in user.
    $this->webUser = $this->drupalCreateUser([
      'view page revisions',
      'revert page revisions',
      'delete page revisions',
      'edit any page content',
      'delete any page content',
      'administer content types',
    ]);

    $this->node = $this->drupalCreateNode();

    // Create a revision for the node
    $this->revision = clone $this->node;
    $this->revision->setNewRevision();
    $this->revision->isDefaultRevision = TRUE;
    $this->revision->save();
  }

  /**
   * Checks cache invalidation on revision delete.
   */
  public function testCacheInvalidationOnRevisionDelete(): void {
    // Visit the page twice to verify that we have cached the page.
    $this->verifyPageCache($this->node->toUrl(), 'MISS');
    $this->verifyPageCache($this->node->toUrl(), 'HIT');

    // Delete one of the nodes revisions.
    $this->drupalLogin($this->webUser);
    $this->drupalGet("node/" . $this->node->id() . "/revisions/" . $this->node->getRevisionId() . "/delete");
    $this->submitForm([], $this->t('Delete'));
    $this->assertSession()->responseContains($this->t('Revision from %revision-date of @type %title has been deleted.', [
      '@type' => 'Basic page',
      '%title' => $this->node->label(),
      '%revision-date' => $this->container->get('date.formatter')->format($this->node->getRevisionCreationTime()),
    ]));
    $this->drupalLogout();

    // Verify that the node page, which is tagged with the node's cache tag, is
    // invalidated and the page is cached anew.
    $this->verifyPageCache($this->node->toUrl(), 'MISS');
    $this->verifyPageCache($this->node->toUrl(), 'HIT');
  }

}
