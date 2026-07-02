<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuTreeStorage;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\Service\MenuTreeMover;
use Drupal\system\Entity\Menu;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\treegrid_reorder\TreegridWorkingCopy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests MenuTreeMover validation and commit paths not covered by FJS tests.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeMoverTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
    'treegrid',
    'treegrid_reorder',
    'menu_tree_ui_test',
    'system',
    'user',
  ];

  /**
   * The mover under test.
   *
   * @var \Drupal\menu_tree_ui\Service\MenuTreeMover
   */
  private MenuTreeMover $mover;

  /**
   * The working copy used to record pending moves for overlay-aware checks.
   */
  private TreegridWorkingCopy $workingCopy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    // The shared tempstore that backs the working copy stamps writes with an
    // owner and REQUEST_TIME; give it a request with a session and an
    // authenticated user so recording a pending move works.
    $request = Request::create('/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->server->set('REQUEST_TIME', 1234567890);
    $this->container->get('request_stack')->push($request);
    $this->setUpCurrentUser(['uid' => 1]);

    Menu::create(['id' => 'test', 'label' => 'Test'])->save();
    Menu::create(['id' => 'other', 'label' => 'Other'])->save();

    // Rebuild so the static (yml-defined) link fixture lands in the tree storage
    // now that its menu exists.
    $this->container->get('plugin.manager.menu.link')->rebuild();

    $this->mover = $this->container->get('menu_tree_ui.mover');
    $this->workingCopy = $this->container->get('menu_tree_ui.working_copy');
  }

  /**
   * Moving a link whose menu_name differs from the route menu must be rejected.
   */
  public function testCrossMenuMoveIsRejected(): void {
    $entity = $this->createLink('main');
    $result = $this->mover->validateMove('other', $entity->getPluginId(), '', NULL);

    $this->assertFalse($result['ok']);
    $this->assertSame('menu_mismatch', $result['code']);
  }

  /**
   * Moving a link whose plugin ID is unknown is rejected.
   */
  public function testUnknownLinkIsNotMoveable(): void {
    $result = $this->mover->validateMove('test', 'does.not.exist', '', NULL);

    $this->assertFalse($result['ok']);
    $this->assertSame('not_moveable', $result['code']);
  }

  /**
   * A static MenuLinkDefault link can be re-parented; the override persists.
   *
   * Static yml-defined links such as the front page are movable now: parent and
   * weight changes are written as overrides through the menu link manager, the
   * same way core's menu overview form reorders module-defined links.
   */
  public function testStaticLinkIsMovable(): void {
    $static_id = 'menu_tree_ui_test.static_fixture';
    $parent = $this->createLink('test');

    $result = $this->commitMove($static_id, $parent->getPluginId(), NULL);
    $this->assertTrue($result['ok'], $result['message'] ?? '');

    // The static link's parent override now points at the content link.
    $definition = $this->container->get('plugin.manager.menu.link')->getDefinition($static_id);
    $this->assertSame($parent->getPluginId(), $definition['parent']);
  }

  /**
   * Moving a link to a parent that belongs to a different menu is rejected.
   *
   * MenuTreeStorage::preSave() derives a link's menu from its parent, so a
   * move into another menu's parent would silently re-home the link if this
   * were not caught.
   */
  public function testMoveToParentInAnotherMenuIsRejected(): void {
    $link = $this->createLink('test');
    $other_parent = $this->createLink('other');

    $result = $this->mover->validateMove('test', $link->getPluginId(), $other_parent->getPluginId(), NULL);

    $this->assertFalse($result['ok']);
    $this->assertSame('parent_menu_mismatch', $result['code']);
  }

  /**
   * Moving to a nonexistent parent is rejected cleanly, not fatally.
   *
   * A bogus new_parent (e.g. a concurrently deleted link) must not reach
   * MenuTreeStorage::getRootPathIds(), which throws a TypeError on an
   * unknown id.
   */
  public function testMoveToNonexistentParentIsRejectedNotFatal(): void {
    $link = $this->createLink('test');

    try {
      $result = $this->mover->validateMove(
        'test',
        $link->getPluginId(),
        'menu_link_content:00000000-0000-0000-0000-000000000000',
        NULL,
      );
    }
    catch (\Throwable $e) {
      $this->fail('validateMove() threw ' . get_class($e) . ': ' . $e->getMessage());
    }

    $this->assertFalse($result['ok']);
    $this->assertSame('unknown_parent', $result['code']);
  }

  /**
   * Using the moved link's own id as before_id must be rejected.
   *
   * The drag path can resolve before_id to the moved link's own id when the
   * drop lands just below the row above it; the server refuses this rather
   * than silently appending the link to the end of the list.
   */
  public function testMoveBeforeItselfIsRejected(): void {
    $entity = $this->createLink('test');
    $id = $entity->getPluginId();

    $result = $this->mover->validateMove('test', $id, '', $id);

    $this->assertFalse($result['ok']);
    $this->assertSame('before_self', $result['code']);
  }

  /**
   * Dropping a link onto itself must be rejected.
   */
  public function testSelfDropIsRejected(): void {
    $entity = $this->createLink('test');
    $id = $entity->getPluginId();

    $result = $this->mover->validateMove('test', $id, $id, NULL);

    $this->assertFalse($result['ok']);
    $this->assertSame('self_target', $result['code']);
  }

  /**
   * Moving a link under its own descendant must be rejected as a cycle.
   */
  public function testCycleIsRejected(): void {
    $parent = $this->createLink('test');
    $child = $this->createLink('test', $parent->getPluginId());

    $result = $this->mover->validateMove(
      'test',
      $parent->getPluginId(),
      $child->getPluginId(),
      NULL,
    );

    $this->assertFalse($result['ok']);
    $this->assertSame('cycle', $result['code']);
  }

  /**
   * A move that would push a link past the depth cap must be rejected.
   */
  public function testDepthCapIsRejected(): void {
    $max = MenuTreeStorage::MAX_DEPTH;
    // Build a chain as deep as the cap allows; the last link sits at the cap.
    $deepest = '';
    for ($i = 0; $i < $max; $i++) {
      $deepest = $this->createLink('test', $deepest)->getPluginId();
    }

    // Anything moved under the deepest link would land one level too deep.
    $link = $this->createLink('test');
    $result = $this->mover->validateMove('test', $link->getPluginId(), $deepest, NULL);

    $this->assertFalse($result['ok']);
    $this->assertSame('too_deep', $result['code']);
  }

  /**
   * The menu_tree_ui_pre_move alter hook must be able to veto the move.
   *
   * The hook receives $veto by reference; setting $veto['veto'] = TRUE blocks
   * the move and returns code 'vetoed' to the caller.
   */
  public function testPreMoveHookCanVeto(): void {
    $entity = $this->createLink('test');

    // Kernel tests cannot install a procedural hook_menu_tree_ui_pre_move() on
    // the fly, so we verify the vetoed path by constructing a mover with a
    // stub handler whose alter() sets the veto.
    $mock_handler = $this->createStub(ModuleHandlerInterface::class);
    $mock_handler
      ->method('alter')
      ->willReturnCallback(function (string $hook, array &$data): void {
        if ($hook === 'menu_tree_ui_pre_move') {
          $data['veto'] = TRUE;
          $data['message'] = 'Test veto.';
        }
      });

    $mover = new MenuTreeMover(
      $this->createStub(MenuTreeStorageInterface::class),
      $mock_handler,
      $this->container->get('menu_tree_ui.loader'),
      $this->container->get('plugin.manager.menu.link'),
      $this->container->get('logger.channel.menu_tree_ui'),
      $this->container->get('string_translation'),
    );

    $result = $mover->validateMove('test', $entity->getPluginId(), '', NULL);

    $this->assertFalse($result['ok']);
    $this->assertSame('vetoed', $result['code']);
  }

  /**
   * Dropping a link above an immovable static sibling keeps it above.
   *
   * Regression test: renumberSiblings() used to query only menu_link_content,
   * so a $before_id pointing at a static (module-defined) link — e.g. the front
   * page — was never matched and the moved link fell through to the bottom of
   * the list. The mover now re-weights every link type via the menu link
   * manager, so the moved link lands immediately before the static sibling.
   */
  public function testDropAboveStaticLinkLandsAboveIt(): void {
    $static_id = 'menu_tree_ui_test.static_fixture';
    // A second content sibling so the level isn't just the moved link + static.
    $this->createLink('test');
    $b = $this->createLink('test');

    $result = $this->commitMove($b->getPluginId(), '', $static_id);
    $this->assertTrue($result['ok'], $result['message'] ?? '');

    $menu_link_manager = $this->container->get('plugin.manager.menu.link');
    $weight_of = static fn(string $id): int => (int) $menu_link_manager->getDefinition($id)['weight'];

    // The moved link sits immediately before the static sibling — proving it
    // landed above it, not dumped at the end of the list as before the fix.
    // (The static link's weight is rewritten too, which is why this holds.)
    $this->assertSame($weight_of($static_id) - 1, $weight_of($b->getPluginId()));
  }

  /**
   * A save that renumbers a level with an orphaned sibling still succeeds.
   *
   * An orphaned link (a menu_tree definition whose backing entity has gone)
   * cannot be re-weighted, but it must not abort the whole save; the remaining
   * links reorder and the move commits.
   */
  public function testRenumberSkipsOrphanedSibling(): void {
    $a = $this->createLink('test');
    $b = $this->createLink('test');
    $c = $this->createLink('test');

    // Orphan B: delete its entity rows directly, leaving the menu_tree
    // definition behind, then drop it from the storage cache so it cannot be
    // loaded — exactly the state that crashed save in the field.
    $id = $b->id();
    $database = $this->container->get('database');
    $database->delete('menu_link_content')->condition('id', $id)->execute();
    $database->delete('menu_link_content_data')->condition('id', $id)->execute();
    $this->container->get('entity_type.manager')
      ->getStorage('menu_link_content')
      ->resetCache([$id]);

    // Moving C above A renumbers the whole root level, which would re-weight the
    // orphaned B. The save must still report success.
    $result = $this->commitMove($c->getPluginId(), '', $a->getPluginId());
    $this->assertTrue($result['ok'], 'The move succeeds despite an orphaned sibling.');

    // C now sits before A in the root order.
    $order = [];
    foreach ($this->container->get('menu_tree_ui.loader')->loadLevel('test', NULL) as $element) {
      $order[] = $element->link->getPluginId();
    }
    $this->assertLessThan(
      array_search($a->getPluginId(), $order, TRUE),
      array_search($c->getPluginId(), $order, TRUE),
      'C was reordered above A even though a sibling was orphaned.',
    );
  }

  /**
   * A pending move that relocates a child unblocks a formerly-cyclic move.
   *
   * The editor sees the overlaid tree, so validation must too: once a pending
   * op has moved C out from under P, moving P under C is no longer a cycle.
   */
  public function testOverlayPreventsFalseCycleRejection(): void {
    $p = $this->createLink('test');
    $c = $this->createLink('test', $p->getPluginId());

    // Baseline: live, C is under P, so moving P under C is a cycle.
    $live = $this->mover->validateMove('test', $p->getPluginId(), $c->getPluginId(), NULL);
    $this->assertFalse($live['ok']);
    $this->assertSame('cycle', $live['code']);

    // Pending: move C out to the root. Now moving P under C is legal.
    $this->workingCopy->addOp('test', [
      'type' => 'move',
      'id' => $c->getPluginId(),
      'new_parent' => '',
      'before_id' => NULL,
    ]);

    $result = $this->mover->validateMove('test', $p->getPluginId(), $c->getPluginId(), NULL);
    $this->assertTrue($result['ok'], $result['message'] ?? '');
  }

  /**
   * A cycle that exists only in the overlay is still rejected.
   *
   * P and C are unrelated live, so moving P under C is fine — until a pending
   * op puts C under P, which makes P an ancestor of C in the overlaid tree.
   */
  public function testOverlayRejectsCycleFormedByPendingMove(): void {
    $p = $this->createLink('test');
    $c = $this->createLink('test');

    // Baseline: siblings at the root, so moving P under C is allowed.
    $live = $this->mover->validateMove('test', $p->getPluginId(), $c->getPluginId(), NULL);
    $this->assertTrue($live['ok'], $live['message'] ?? '');

    // Pending: move C under P. P is now C's ancestor in the overlay.
    $this->workingCopy->addOp('test', [
      'type' => 'move',
      'id' => $c->getPluginId(),
      'new_parent' => $p->getPluginId(),
      'before_id' => NULL,
    ]);

    $result = $this->mover->validateMove('test', $p->getPluginId(), $c->getPluginId(), NULL);
    $this->assertFalse($result['ok']);
    $this->assertSame('cycle', $result['code']);
  }

  /**
   * The depth cap is measured on the overlaid tree, not live storage.
   *
   * A pending move that deepens a parent must push a later move under it over
   * the cap even though the same move fits against live storage.
   */
  public function testOverlayDepthUsesPendingParent(): void {
    $max = MenuTreeStorage::MAX_DEPTH;
    // A chain (max - 1) deep at the root: the deepest link sits at depth max-1.
    $chain_root = $this->createLink('test');
    $deepest = $chain_root->getPluginId();
    for ($i = 1; $i < $max - 1; $i++) {
      $deepest = $this->createLink('test', $deepest)->getPluginId();
    }
    $leaf = $this->createLink('test');
    $x = $this->createLink('test');

    // Baseline: live, a leaf under the deepest link lands at depth max — OK.
    $live = $this->mover->validateMove('test', $leaf->getPluginId(), $deepest, NULL);
    $this->assertTrue($live['ok'], $live['message'] ?? '');

    // Pending: move the chain one level deeper under X, so the deepest link now
    // sits at depth max and a leaf under it would exceed the cap.
    $this->workingCopy->addOp('test', [
      'type' => 'move',
      'id' => $chain_root->getPluginId(),
      'new_parent' => $x->getPluginId(),
      'before_id' => NULL,
    ]);

    $result = $this->mover->validateMove('test', $leaf->getPluginId(), $deepest, NULL);
    $this->assertFalse($result['ok']);
    $this->assertSame('too_deep', $result['code']);
  }

  // Helpers.

  /**
   * Commits a single move through the working-copy path (the live write path).
   *
   * @param string $id
   *   Plugin ID of the link to move.
   * @param string $new_parent
   *   New parent plugin ID, or '' for the root.
   * @param string|null $before_id
   *   Sibling to sit before, or NULL for the end.
   *
   * @return array
   *   The commitWorkingCopy() outcome.
   */
  private function commitMove(string $id, string $new_parent, ?string $before_id): array {
    return $this->mover->commitWorkingCopy('test', [
      ['type' => 'move', 'id' => $id, 'new_parent' => $new_parent, 'before_id' => $before_id],
    ]);
  }

  /**
   * Creates and saves a menu_link_content entity in the given menu.
   *
   * @param string $menu_name
   *   The menu to create the link in.
   * @param string $parent
   *   The parent link plugin ID, or '' for the top level.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface
   *   The saved link.
   */
  private function createLink(string $menu_name, string $parent = ''): MenuLinkContentInterface {
    $entity = MenuLinkContent::create([
      'title' => 'Test link',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => $menu_name,
      'parent' => $parent,
    ]);
    $entity->save();
    return $entity;
  }

}
