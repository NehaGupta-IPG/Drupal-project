<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\Service\MenuTreeMover;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests MenuTreeMover validation paths that are not exercised by FJS tests.
 *
 * @group menu_tree_ui
 */
#[RunTestsInSeparateProcesses]
final class MenuTreeMoverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
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
    $result = $this->mover->move('other', $entity->getPluginId(), '', NULL);

    $this->assertFalse($result['ok']);
    $this->assertSame('menu_mismatch', $result['code']);
  }

  /**
   * Moving a link whose plugin ID is unknown is rejected.
   */
  public function testUnknownLinkIsNotMoveable(): void {
    $result = $this->mover->move('test', 'does.not.exist', '', NULL);

    $this->assertFalse($result['ok']);
    $this->assertSame('not_moveable', $result['code']);
  }

  /**
   * A static MenuLinkDefault link can be reparented; the override persists.
   *
   * Static yml-defined links such as the front page are movable now: parent and
   * weight changes are written as overrides through the menu link manager, the
   * same way core's menu overview form reorders module-defined links.
   */
  public function testStaticLinkIsMovable(): void {
    $static_id = 'menu_tree_ui_test.static_fixture';
    $parent = $this->createLink('test');

    $result = $this->mover->move('test', $static_id, $parent->getPluginId(), NULL);
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

    $result = $this->mover->move('test', $id, $id, NULL);

    $this->assertFalse($result['ok']);
    $this->assertSame('self_target', $result['code']);
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
      $this->container->get('database'),
      $mock_handler,
      $this->container->get('menu_tree_ui.loader'),
      $this->container->get('plugin.manager.menu.link'),
    );

    $result = $mover->move('test', $entity->getPluginId(), '', NULL);

    $this->assertFalse($result['ok']);
    $this->assertSame('vetoed', $result['code']);
  }

  /**
   * Dropping a link above an immovable static sibling keeps it above.
   *
   * Regression test: renumberSiblings() used to query only menu_link_content,
   * so a $before_id pointing at a static (module-defined) link — e.g. the front
   * page — was never matched and the moved link fell through to the bottom of
   * the list. The mover now reweights every link type via the menu link
   * manager, so the moved link lands immediately before the static sibling.
   */
  public function testDropAboveStaticLinkLandsAboveIt(): void {
    $static_id = 'menu_tree_ui_test.static_fixture';
    // A second content sibling so the level isn't just the moved link + static.
    $this->createLink('test');
    $b = $this->createLink('test');

    $result = $this->mover->move('test', $b->getPluginId(), '', $static_id);
    $this->assertTrue($result['ok'], $result['message'] ?? '');

    $menu_link_manager = $this->container->get('plugin.manager.menu.link');
    $weight_of = static fn(string $id): int => (int) $menu_link_manager->getDefinition($id)['weight'];

    // The moved link sits immediately before the static sibling — proving it
    // landed above it, not dumped at the end of the list as before the fix.
    // (The static link's weight is rewritten too, which is why this holds.)
    $this->assertSame($weight_of($static_id) - 1, $weight_of($b->getPluginId()));
    $this->assertSame($weight_of($b->getPluginId()), $result['weight']);
  }

  // Helpers.

  /**
   * Creates and saves a menu_link_content entity in the given menu.
   */
  private function createLink(string $menu_name): MenuLinkContentInterface {
    $entity = MenuLinkContent::create([
      'title' => 'Test link',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => $menu_name,
    ]);
    $entity->save();
    return $entity;
  }

}
