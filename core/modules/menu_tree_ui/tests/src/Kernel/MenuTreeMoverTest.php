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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests MenuTreeMover validation and commit paths not covered by FJS tests.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeMoverTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    Menu::create(['id' => 'test', 'label' => 'Test'])->save();
    Menu::create(['id' => 'other', 'label' => 'Other'])->save();

    // Rebuild so the static (yml-defined) link fixture lands in the tree storage
    // now that its menu exists.
    $this->container->get('plugin.manager.menu.link')->rebuild();

    $this->mover = $this->container->get('menu_tree_ui.mover');
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
