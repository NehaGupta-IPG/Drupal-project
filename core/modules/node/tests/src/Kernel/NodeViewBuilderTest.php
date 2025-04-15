<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Kernel;

use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeStorageInterface;
use Drupal\Tests\AutowireProperty;
use Drupal\user\Entity\User;

/**
 * Tests the node view builder.
 *
 * @group node
 *
 * @coversDefaultClass \Drupal\node\NodeViewBuilder
 */
class NodeViewBuilderTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * The node storage.
   */
  #[AutowireProperty(expression: "service('entity_type.manager').getStorage('node')")]
  protected NodeStorageInterface $storage;

  /**
   * The node view builder.
   */
  #[AutowireProperty(expression: "service('entity_type.manager').getViewBuilder('node')")]
  protected EntityViewBuilderInterface $viewBuilder;

  /**
   * The renderer.
   */
  #[AutowireProperty]
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $type->save();

    $this->installSchema('node', 'node_access');
    $this->installConfig(['system', 'node']);
  }

  /**
   * Tests that node links are displayed correctly in pending revisions.
   *
   * @covers ::buildComponents
   * @covers ::renderLinks
   * @covers ::buildLinks
   */
  public function testPendingRevisionLinks(): void {
    $account = User::create([
      'name' => $this->randomString(),
    ]);
    $account->save();

    $title = $this->randomMachineName();
    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'uid' => $account->id(),
    ]);
    $node->save();

    /** @var \Drupal\node\NodeInterface $pending_revision */
    $pending_revision = $this->storage->createRevision($node, FALSE);
    $draft_title = $title . ' draft';
    $pending_revision->setTitle($draft_title);
    $pending_revision->save();

    $build = $this->viewBuilder->view($node, 'teaser');
    $output = (string) $this->renderer->renderInIsolation($build);
    $this->assertStringContainsString("title=\"$title\"", $output);

    $build = $this->viewBuilder->view($pending_revision, 'teaser');
    $output = (string) $this->renderer->renderInIsolation($build);
    $this->assertStringContainsString("title=\"$draft_title\"", $output);
  }

}
