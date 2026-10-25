<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\Controller\MenuTreeController;
use Drupal\menu_tree_ui\Service\MenuTreeLoader;
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
 * Tests the working-copy service and its Save-time commit replay.
 *
 * Covers recording/clearing pending ops, the shared-tempstore lock that keeps a
 * second user from writing, and committing the recorded moves to live storage:
 * Save validates the recorded moves in order and writes nothing if any fails.
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
    'menu_tree_ui_hook_test',
    'treegrid',
    'treegrid_reorder',
    'system',
    'user',
  ];

  /**
   * The working copy under test.
   */
  private TreegridWorkingCopy $workingCopy;

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

    // The sequential validation caught the bad op before anything was written,
    // so the valid first op was never applied: live order is unchanged.
    $this->assertSame([$a, $b, $c], $this->idsOf($this->loader->loadLevel('test', NULL)));
  }

  /**
   * A second user cannot clear another user's working copy; breakLock() can.
   */
  public function testClearRespectsOwnershipButBreakLockDoesNot(): void {
    $owner = $this->createUser([], 'owner');
    $other = $this->createUser([], 'other');

    $this->setCurrentUser($owner);
    $this->assertTrue($this->workingCopy->addOp('test', ['type' => 'move', 'id' => 'one']));

    $this->setCurrentUser($other);
    $this->workingCopy->clear('test');
    $this->assertTrue($this->workingCopy->hasChanges('test'), "Another user's clear() must not discard the owner's working copy.");
    $this->assertSame(['one'], array_column($this->workingCopy->getOps('test'), 'id'));

    $this->workingCopy->breakLock('test');
    $this->assertFalse($this->workingCopy->hasChanges('test'));
    $this->assertNull($this->workingCopy->getLockMetadata('test'));
  }

  /**
   * A batch whose later move is only valid after an earlier one saves.
   */
  public function testDependentCycleBatchSaves(): void {
    $parent = $this->createLink('Heron', 10)->getPluginId();
    $child = $this->createLink('Plover', 0, $parent)->getPluginId();

    $this->recordMove($child, '');
    $this->recordMove($parent, $child);

    $result = $this->save();
    $this->assertTrue($result['ok'], $result['message'] ?? '');
    $this->assertSame('', $this->parentOf($child));
    $this->assertSame($child, $this->parentOf($parent));
  }

  /**
   * A batch whose later move only fits the depth cap after an earlier one.
   */
  public function testDependentDepthBatchSaves(): void {
    $chain = $this->createChain(8);
    $branch = $this->createLink('Curlew', 20)->getPluginId();
    $this->createLink('Godwit', 0, $branch);

    $this->recordMove($chain[4], '');
    $this->recordMove($branch, $chain[7]);

    $result = $this->save();
    $this->assertTrue($result['ok'], $result['message'] ?? '');
    $this->assertSame($chain[7], $this->parentOf($branch));
  }

  /**
   * Pending moves under a link count toward its height at record time.
   */
  public function testPendingDescendantsCountTowardDepthAtRecordTime(): void {
    $chain = $this->createChain(8);
    $x = $this->createLink('Dunlin', 20)->getPluginId();
    $y = $this->createLink('Knot', 21)->getPluginId();

    $this->recordMove($x, $y);
    $validation = $this->mover->validateMove('test', $y, $chain[7], NULL);
    $this->assertFalse($validation['ok']);
    $this->assertSame('too_deep', $validation['code']);

    // A client that records the move anyway is stopped when Save validates.
    $this->recordMove($y, $chain[7]);
    $result = $this->save();
    $this->assertFalse($result['ok']);
    $this->assertSame(1, $result['failed_op']);
    $this->assertSame('', $this->parentOf($x));
    $this->assertSame('', $this->parentOf($y));
  }

  /**
   * A move whose pending subtree lands exactly on the depth cap is accepted.
   */
  public function testPendingDescendantsReachingDepthCapAreAccepted(): void {
    $chain = $this->createChain(7);
    $x = $this->createLink('Sanderling', 20)->getPluginId();
    $y = $this->createLink('Turnstone', 21)->getPluginId();

    $this->recordMove($x, $y);
    $validation = $this->mover->validateMove('test', $y, $chain[6], NULL);
    $this->assertTrue($validation['ok']);
  }

  /**
   * A live change that invalidates a recorded move stops Save writing anything.
   */
  public function testLiveChangeBeforeSaveWritesNothing(): void {
    $a = $this->createLink('Osprey', 20);
    $a_id = $a->getPluginId();
    $b_id = $this->createLink('Kestrel', 21)->getPluginId();
    $c_id = $this->createLink('Merlin', 22)->getPluginId();

    $this->recordMove($a_id, $b_id);
    $this->recordMove($c_id, '', $a_id);
    $this->container->get('plugin.manager.menu.link')->updateDefinition($b_id, ['parent' => $a_id]);

    $result = $this->save();
    $this->assertFalse($result['ok']);
    $this->assertSame(0, $result['failed_op']);
    $this->assertStringContainsString('Osprey', $result['message']);
    $this->assertSame('', $this->parentOf($a_id));
    $this->assertSame($a_id, $this->parentOf($b_id));
    $this->assertSame(22, (int) $this->container->get('plugin.manager.menu.link')->getDefinition($c_id)['weight']);
    $this->assertCount(2, $this->workingCopy->getOps('test'));
  }

  /**
   * A move that fails while applying drops the already-written moves.
   *
   * Both moves validate in sequence. A module hook then re-parents a link
   * after the first move is written, so the second move fails in the apply
   * loop: the scenario MenuTreeController::save() must reconcile.
   */
  public function testPartialCommitDropsAppliedOps(): void {
    $a_id = $this->createLink('Swift', 20)->getPluginId();
    $b_id = $this->createLink('Martin', 21)->getPluginId();
    $c_id = $this->createLink('Swallow', 22)->getPluginId();

    $this->recordMove($a_id, $b_id);
    $this->recordMove($c_id, $a_id);
    $this->container->get('state')->set('menu_tree_ui_hook_test.change_parent_after_move', [
      'after' => $a_id,
      'link' => $b_id,
      'parent' => $c_id,
    ]);

    $result = $this->save();

    $this->assertFalse($result['ok']);
    $this->assertArrayNotHasKey('dirty', $result);
    $this->assertSame([0], $result['applied_ops']);
    $this->assertStringContainsString('Saved 1 of 2', $result['message']);
    $this->assertSame($b_id, $this->parentOf($a_id));

    // The applied op is gone from the working copy; the failed one remains
    // pending for a retry.
    $remaining = $this->workingCopy->getOps('test');
    $this->assertCount(1, $remaining);
    $this->assertSame($c_id, $remaining[0]['id']);
  }

  /**
   * Successful Save and Cancel responses say whether changes remain.
   */
  public function testSaveAndCancelReportWhetherChangesRemain(): void {
    $a_id = $this->createLink('Pelican', 30)->getPluginId();
    $b_id = $this->createLink('Heron', 31)->getPluginId();

    $this->recordMove($a_id, $b_id);
    $cancel = json_decode((string) MenuTreeController::create($this->container)->cancel(Menu::load('test'))->getContent(), TRUE);
    $this->assertTrue($cancel['ok']);
    $this->assertFalse($cancel['dirty']);

    $this->recordMove($a_id, $b_id);
    $result = $this->save();
    $this->assertTrue($result['ok']);
    $this->assertFalse($result['dirty']);
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

  /**
   * Creates a chain of nested links, each the child of the one before.
   *
   * @return string[]
   *   The plugin IDs, outermost first.
   */
  private function createChain(int $length): array {
    $ids = [];
    $parent = '';
    for ($level = 1; $level <= $length; $level++) {
      $parent = $this->createLink("Chain link $level", 0, $parent)->getPluginId();
      $ids[] = $parent;
    }
    return $ids;
  }

  /**
   * Records a move in the working copy without validating it.
   */
  private function recordMove(string $id, string $new_parent, ?string $before_id = NULL): void {
    $this->assertTrue($this->workingCopy->addOp('test', [
      'type' => 'move',
      'id' => $id,
      'new_parent' => $new_parent,
      'before_id' => $before_id,
    ]));
  }

  /**
   * Saves the test menu through the controller and decodes the response.
   *
   * @return array<string, mixed>
   *   The decoded JSON outcome.
   */
  private function save(): array {
    $response = MenuTreeController::create($this->container)->save(Menu::load('test'));
    return json_decode((string) $response->getContent(), TRUE);
  }

  /**
   * The live parent plugin ID of a link.
   */
  private function parentOf(string $plugin_id): string {
    return (string) $this->container->get('plugin.manager.menu.link')->getDefinition($plugin_id)['parent'];
  }

}
