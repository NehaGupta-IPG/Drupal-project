<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\Service\MenuTreeLoader;
use Drupal\system\Entity\Menu;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\treegrid_reorder\TreegridWorkingCopy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests that loadLevelForEditing() applies pending working-copy moves.
 *
 * The moves are replayed on one lazily-loaded level: they must reorder within
 * a parent, drop a link that moved out, and surface a link that moved in,
 * without touching live storage (loadLevel()). The result is counted and
 * sliced.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeOverlayTest extends KernelTestBase {

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
    'system',
    'user',
  ];

  /**
   * The loader under test.
   */
  private MenuTreeLoader $loader;

  /**
   * The working copy used to record pending moves.
   */
  private TreegridWorkingCopy $workingCopy;

  /**
   * Top-level link plugin IDs, in their live order.
   *
   * @var string[]
   */
  private array $ids = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    // The shared tempstore stamps writes with REQUEST_TIME and an owner; give
    // it a request (with a session, which KernelTestBase tears down) and an
    // authenticated user so neither is missing.
    $request = Request::create('/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->server->set('REQUEST_TIME', 1234567890);
    $this->container->get('request_stack')->push($request);
    $this->setUpCurrentUser(['uid' => 1]);

    Menu::create(['id' => 'test', 'label' => 'Test'])->save();
    $this->container->get('plugin.manager.menu.link')->rebuild();

    // Three top-level links in a known order.
    $this->ids = [
      $this->createLink('A', 0)->getPluginId(),
      $this->createLink('B', 1)->getPluginId(),
      $this->createLink('C', 2)->getPluginId(),
    ];

    $this->loader = $this->container->get('menu_tree_ui.loader');
    $this->workingCopy = $this->container->get('menu_tree_ui.working_copy');
  }

  /**
   * With no pending ops, the editing read equals the live read.
   */
  public function testNoOverlayWhenEmpty(): void {
    $this->assertSame($this->ids, $this->idsOf($this->loader->loadLevel('test', NULL)));
    $this->assertSame($this->ids, $this->idsOf($this->loader->loadLevelForEditing('test', NULL)->elements));
  }

  /**
   * A pending reorder within a parent shows in the editing read only.
   */
  public function testOverlayReordersWithinParent(): void {
    [$a, $b, $c] = $this->ids;
    // Move C to sit before A: expected order C, A, B.
    $this->workingCopy->addOp('test', [
      'type' => 'move',
      'id' => $c,
      'new_parent' => '',
      'before_id' => $a,
    ]);

    $this->assertSame([$c, $a, $b], $this->idsOf($this->loader->loadLevelForEditing('test', NULL)->elements));
    // Live storage is untouched.
    $this->assertSame([$a, $b, $c], $this->idsOf($this->loader->loadLevel('test', NULL)));
  }

  /**
   * A pending re-parent drops the link from its old level and adds it to the new.
   */
  public function testOverlayMovesLinkBetweenLevels(): void {
    [$a, $b, $c] = $this->ids;
    // Move C under A.
    $this->workingCopy->addOp('test', [
      'type' => 'move',
      'id' => $c,
      'new_parent' => $a,
      'before_id' => NULL,
    ]);

    // C is gone from the top level.
    $this->assertSame([$a, $b], $this->idsOf($this->loader->loadLevelForEditing('test', NULL)->elements));
    // C is now a child of A, materialized from live storage.
    $this->assertSame([$c], $this->idsOf($this->loader->loadLevelForEditing('test', $a)->elements));
  }

  /**
   * A materialized (pending-moved-in) element carries the destination depth.
   *
   * A is a top-level link (depth 1), so C's materialized element at its new
   * position under A must report depth 2, not the link's own stale live depth
   * or a hardcoded 1 — OperationsColumn relies on this for its "Add child"
   * depth-cap check.
   */
  public function testOverlayMaterializedElementHasCorrectDepth(): void {
    [$a, , $c] = $this->ids;
    $this->workingCopy->addOp('test', [
      'type' => 'move',
      'id' => $c,
      'new_parent' => $a,
      'before_id' => NULL,
    ]);

    $children = $this->loader->loadLevelForEditing('test', $a)->elements;
    $this->assertCount(1, $children);
    $this->assertSame(2, $children[0]->depth);
  }

  /**
   * The slice is cut from the whole overlaid level.
   */
  public function testSliceOfOverlaidLevel(): void {
    [$a, $b, $c] = $this->ids;
    $d = $this->createLink('D', 3)->getPluginId();
    $e = $this->createLink('E', 4)->getPluginId();
    $this->workingCopy->addOp('test', ['type' => 'move', 'id' => $c, 'new_parent' => '', 'before_id' => NULL]);

    $slice = $this->loader->loadLevelForEditing('test', NULL, 2, 2);
    $this->assertSame([$d, $e], $this->idsOf($slice->elements));
    $this->assertSame(5, $slice->total);
    $this->assertSame([$a, $b, $d, $e, $c], $slice->ids);
    $this->assertSame($c, $slice->nextId);

    $last = $this->loader->loadLevelForEditing('test', NULL, 4, 2);
    $this->assertSame([$c], $this->idsOf($last->elements));
    $this->assertNull($last->nextId);
  }

  /**
   * A link moved in from a child level is counted and sits at its anchor.
   */
  public function testMovedInLinkIsCounted(): void {
    [$a, $b, $c] = $this->ids;
    $child = $this->createLink('Child', 0, $a)->getPluginId();
    $this->workingCopy->addOp('test', ['type' => 'move', 'id' => $child, 'new_parent' => '', 'before_id' => $b]);

    $slice = $this->loader->loadLevelForEditing('test', NULL, 0, 10);
    $this->assertSame(4, $slice->total);
    $this->assertSame([$a, $child, $b, $c], $slice->ids);
    $this->assertSame([$a, $child, $b, $c], $this->idsOf($slice->elements));
  }

  /**
   * A deleted moved-in link is dropped and a move anchored on it goes last.
   */
  public function testDeletedMovedInLink(): void {
    [$a, $b, $c] = $this->ids;
    $x = $this->createLink('X', 0, $a);
    $y = $this->createLink('Y', 1, $a)->getPluginId();
    $x_id = $x->getPluginId();
    $this->workingCopy->addOp('test', ['type' => 'move', 'id' => $x_id, 'new_parent' => '', 'before_id' => $b]);
    $this->workingCopy->addOp('test', ['type' => 'move', 'id' => $y, 'new_parent' => '', 'before_id' => $x_id]);
    $x->delete();
    $this->container->get('plugin.manager.menu.link')->removeDefinition($x_id, FALSE);

    $slice = $this->loader->loadLevelForEditing('test', NULL);
    $this->assertSame([$a, $b, $c, $y], $slice->ids);
    $this->assertSame([$a, $b, $c, $y], $this->idsOf($slice->elements));
    $this->assertSame(4, $slice->total);
  }

  /**
   * An offset past the end returns no elements and the right total.
   */
  public function testOffsetPastEnd(): void {
    $slice = $this->loader->loadLevelForEditing('test', NULL, 10, 2);
    $this->assertSame([], $slice->elements);
    $this->assertSame(3, $slice->total);
    $this->assertNull($slice->nextId);
  }

  // Helpers.

  /**
   * Maps tree elements to their plugin IDs.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $elements
   *   The elements.
   *
   * @return string[]
   *   The plugin IDs, in order.
   */
  private function idsOf(array $elements): array {
    return array_map(
      static fn(MenuLinkTreeElement $e): string => $e->link->getPluginId(),
      array_values($elements),
    );
  }

  /**
   * Creates and saves a menu_link_content entity in the test menu.
   */
  private function createLink(string $title, int $weight, string $parent = ''): MenuLinkContentInterface {
    $entity = MenuLinkContent::create([
      'title' => $title,
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'test',
      'weight' => $weight,
      'parent' => $parent,
    ]);
    $entity->save();
    return $entity;
  }

}
