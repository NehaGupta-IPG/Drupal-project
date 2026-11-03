<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\treegrid_reorder\TreegridWorkingCopy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the generic working copy: ordered op recording, clearing, and locking.
 */
#[Group('treegrid_reorder')]
#[RunTestsInSeparateProcesses]
final class TreegridWorkingCopyTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'treegrid_reorder',
    'system',
    'user',
  ];

  /**
   * The working copy under test, on a test collection.
   */
  private TreegridWorkingCopy $workingCopy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');

    $request = Request::create('/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->server->set('REQUEST_TIME', 1234567890);
    $this->container->get('request_stack')->push($request);
    $this->setUpCurrentUser(['uid' => 1]);

    $this->workingCopy = new TreegridWorkingCopy(
      $this->container->get('tempstore.shared'),
      $this->container->get('current_user'),
      'treegrid_reorder_test',
    );
  }

  /**
   * Ops are recorded in order, reported as changes, and cleared.
   */
  public function testRecordAndClear(): void {
    $this->assertFalse($this->workingCopy->hasChanges('alpha'));

    $this->workingCopy->addOp('alpha', ['type' => 'move', 'id' => 'one']);
    $this->workingCopy->addOp('alpha', ['type' => 'move', 'id' => 'two']);

    $this->assertTrue($this->workingCopy->hasChanges('alpha'));
    $this->assertSame(['one', 'two'], array_column($this->workingCopy->getOps('alpha'), 'id'));

    $this->workingCopy->clear('alpha');
    $this->assertFalse($this->workingCopy->hasChanges('alpha'));
    $this->assertSame([], $this->workingCopy->getOps('alpha'));
  }

  /**
   * The view reflects recorded moves, and a cleared copy changes nothing.
   */
  public function testViewReflectsMoves(): void {
    $this->workingCopy->addOp('alpha', ['type' => 'move', 'id' => 'c', 'new_parent' => '', 'before_id' => 'a']);

    $view = $this->workingCopy->view('alpha');
    $this->assertSame(['c', 'a', 'b'], $view->levelIds('', ['a', 'b', 'c'], ['c']));
    $this->assertSame('', $view->pendingParent('c'));
    $this->assertSame(['c'], $view->pendingIds());

    $this->workingCopy->clear('alpha');
    $view = $this->workingCopy->view('alpha');
    $this->assertSame(['a', 'b', 'c'], $view->levelIds('', ['a', 'b', 'c'], []));
    $this->assertNull($view->pendingParent('c'));
  }

  /**
   * Views of different tree ids don't see each other's moves.
   */
  public function testViewsAreIsolated(): void {
    $this->workingCopy->addOp('alpha', ['type' => 'move', 'id' => 'c', 'new_parent' => '', 'before_id' => 'a']);

    $this->assertSame(['a', 'b', 'c'], $this->workingCopy->view('beta')->levelIds('', ['a', 'b', 'c'], []));
    $this->assertNull($this->workingCopy->view('beta')->pendingParent('c'));
  }

  /**
   * Ids within a collection are independent of one another.
   */
  public function testIdsAreIsolated(): void {
    $this->workingCopy->addOp('alpha', ['type' => 'move', 'id' => 'one']);

    $this->assertTrue($this->workingCopy->hasChanges('alpha'));
    $this->assertFalse($this->workingCopy->hasChanges('beta'));
  }

  /**
   * A second user is locked out of writing while another holds the copy.
   */
  public function testLockBlocksOtherUser(): void {
    $owner = $this->createUser([], 'owner');
    $other = $this->createUser([], 'other');

    $this->setCurrentUser($owner);
    $this->assertTrue($this->workingCopy->addOp('alpha', ['type' => 'move', 'id' => 'one']));
    $this->assertFalse($this->workingCopy->isLocked('alpha'));

    $this->setCurrentUser($other);
    $this->assertTrue($this->workingCopy->isLocked('alpha'));
    $this->assertFalse($this->workingCopy->addOp('alpha', ['type' => 'move', 'id' => 'two']));
    $this->assertSame(
      (string) $owner->id(),
      (string) $this->workingCopy->getLockMetadata('alpha')->getOwnerId(),
    );
  }

  /**
   * A second user cannot clear another user's working copy; breakLock() can.
   */
  public function testClearRespectsOwnershipButBreakLockDoesNot(): void {
    $owner = $this->createUser([], 'owner');
    $other = $this->createUser([], 'other');

    $this->setCurrentUser($owner);
    $this->assertTrue($this->workingCopy->addOp('alpha', ['type' => 'move', 'id' => 'one']));

    $this->setCurrentUser($other);
    $this->workingCopy->clear('alpha');
    $this->assertTrue($this->workingCopy->hasChanges('alpha'), "Another user's clear() must not discard the owner's working copy.");
    $this->assertSame(['one'], array_column($this->workingCopy->getOps('alpha'), 'id'));

    $this->workingCopy->breakLock('alpha');
    $this->assertFalse($this->workingCopy->hasChanges('alpha'));
    $this->assertNull($this->workingCopy->getLockMetadata('alpha'));
  }

}
