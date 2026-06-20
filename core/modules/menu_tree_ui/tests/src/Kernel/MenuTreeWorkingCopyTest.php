<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\Service\MenuTreeLoader;
use Drupal\menu_tree_ui\Service\MenuTreeMover;
use Drupal\menu_tree_ui\Service\MenuTreeWorkingCopy;
use Drupal\system\Entity\Menu;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the working-copy service and its Save-time commit replay.
 *
 * Covers recording/clearing pending ops, the shared-tempstore lock that keeps a
 * second user from writing, and committing the recorded moves to live storage
 * atomically — including rollback when a recorded op no longer validates.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeWorkingCopyTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
    'treegrid',
    'system',
    'user',
  ];

  /**
   * The working copy under test.
   */
  private MenuTreeWorkingCopy $workingCopy;

  /**
   * The mover, for the commit-replay tests.
   */
  private MenuTreeMover $mover;

  /**
   * The loader, for asserting committed live order.
   */
  private MenuTreeLoader $loader;

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

    $request = Request::create('/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->server->set('REQUEST_TIME', 1234567890);
    $this->container->get('request_stack')->push($request);
    $this->setUpCurrentUser(['uid' => 1]);

    Menu::create(['id' => 'test', 'label' => 'Test'])->save();
    $this->container->get('plugin.manager.menu.link')->rebuild();

    $this->ids = [
      $this->createLink('A', 0)->getPluginId(),
      $this->createLink('B', 1)->getPluginId(),
      $this->createLink('C', 2)->getPluginId(),
    ];

    $this->workingCopy = $this->container->get('menu_tree_ui.working_copy');
    $this->mover = $this->container->get('menu_tree_ui.mover');
    $this->loader = $this->container->get('menu_tree_ui.loader');
  }

  /**
   * Ops are recorded in order, reported as changes, and cleared.
   */
  public function testRecordAndClear(): void {
    $this->assertFalse($this->workingCopy->hasChanges('test'));

    $this->workingCopy->addOp('test', ['type' => 'move', 'id' => 'one']);
    $this->workingCopy->addOp('test', ['type' => 'move', 'id' => 'two']);

    $this->assertTrue($this->workingCopy->hasChanges('test'));
    $ops = $this->workingCopy->getOps('test');
    $this->assertSame(['one', 'two'], array_column($ops, 'id'));

    $this->workingCopy->clear('test');
    $this->assertFalse($this->workingCopy->hasChanges('test'));
    $this->assertSame([], $this->workingCopy->getOps('test'));
  }

  /**
   * A second user is locked out of writing while another holds the copy.
   */
  public function testLockBlocksOtherUser(): void {
    $owner = $this->createUser([], 'owner');
    $other = $this->createUser([], 'other');

    $this->setCurrentUser($owner);
    $this->assertTrue($this->workingCopy->addOp('test', ['type' => 'move', 'id' => 'one']));
    $this->assertFalse($this->workingCopy->isLocked('test'));

    $this->setCurrentUser($other);
    $this->assertTrue($this->workingCopy->isLocked('test'));
    $this->assertFalse($this->workingCopy->addOp('test', ['type' => 'move', 'id' => 'two']));
    $this->assertSame((string) $owner->id(), (string) $this->workingCopy->getLockMetadata('test')->getOwnerId());
  }

  /**
   * Committing replays the recorded moves onto live storage.
   */
  public function testCommitAppliesMoves(): void {
    [$a, $b, $c] = $this->ids;
    $this->workingCopy->addOp('test', [
      'type' => 'move',
      'id' => $c,
      'new_parent' => '',
      'before_id' => $a,
    ]);

    $result = $this->mover->commitWorkingCopy('test', $this->workingCopy->getOps('test'));
    $this->assertTrue($result['ok'], $result['message'] ?? '');

    // Live order now reflects the reorder: C, A, B.
    $this->assertSame([$c, $a, $b], $this->idsOf($this->loader->loadLevel('test', NULL)));
  }

  /**
   * An individually invalid op aborts the commit before anything is applied.
   */
  public function testInvalidOpAbortsCommit(): void {
    [$a, $b, $c] = $this->ids;
    // First op is valid; the second targets an unknown link and must fail.
    $this->workingCopy->addOp('test', [
      'type' => 'move',
      'id' => $c,
      'new_parent' => '',
      'before_id' => $a,
    ]);
    $this->workingCopy->addOp('test', [
      'type' => 'move',
      'id' => 'does.not.exist',
      'new_parent' => '',
      'before_id' => NULL,
    ]);

    $result = $this->mover->commitWorkingCopy('test', $this->workingCopy->getOps('test'));
    $this->assertFalse($result['ok']);
    $this->assertSame(1, $result['failed_op']);

    // Pre-flight validation caught the bad op, so the valid first op was never
    // applied: live order is unchanged.
    $this->assertSame([$a, $b, $c], $this->idsOf($this->loader->loadLevel('test', NULL)));
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
