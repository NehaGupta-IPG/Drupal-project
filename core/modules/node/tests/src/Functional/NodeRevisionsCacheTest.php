<?php

namespace Drupal\Tests\node\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\system\Functional\Cache\PageCacheTagsTestBase;

/**
 * Create a node with revisions and test cache invalidation during CRUD
 * operations.
 *
 * @group node
 */
class NodeRevisionsCacheTest extends PageCacheTagsTestBase {

  use StringTranslationTrait;

  /**
   * The user that with administration access on revisions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * The initial node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The node revision.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $revision;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'datetime'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
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
  public function testCacheInvalidationOnRevisionDelete() {
    // Visit the page twice to verify that we have cached the page.
    $this->verifyPageCache($this->node->toUrl(), 'MISS');
    $this->verifyPageCache($this->node->toUrl(), 'HIT');

    // Delete one of the nodes revisions.
    $this->drupalLogin($this->webUser);
    $this->drupalPostForm("node/" . $this->node->id() . "/revisions/" . $this->node->getRevisionId() . "/delete", [], t('Delete'));
    $this->assertRaw($this->t('Revision from %revision-date of @type %title has been deleted.', [
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
