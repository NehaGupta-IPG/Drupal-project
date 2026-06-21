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
 * Tests that loadLevelForEditing() overlays pending working-copy moves.
 *
 * The overlay is a pure replay of recorded moves onto one lazily-loaded level:
 * it must reorder within a parent, drop a link that moved out, and surface a
 * link that moved in — without touching live storage (loadLevel()).
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
    $this->assertSame($this->ids, $this->idsOf($this->loader->loadLevelForEditing('test', NULL)));
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

    $this->assertSame([$c, $a, $b], $this->idsOf($this->loader->loadLevelForEditing('test', NULL)));
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
    $this->assertSame([$a, $b], $this->idsOf($this->loader->loadLevelForEditing('test', NULL)));
    // C is now a child of A, materialized from live storage.
    $this->assertSame([$c], $this->idsOf($this->loader->loadLevelForEditing('test', $a)));
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
   * Creates and saves a top-level menu_link_content entity in the test menu.
   */
  private function createLink(string $title, int $weight): MenuLinkContentInterface {
    $entity = MenuLinkContent::create([
      'title' => $title,
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'test',
      'weight' => $weight,
    ]);
    $entity->save();
    return $entity;
  }

}
