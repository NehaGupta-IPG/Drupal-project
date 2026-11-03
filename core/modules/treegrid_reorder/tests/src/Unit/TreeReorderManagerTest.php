<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\Unit;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\UnitTestCase;
use Drupal\treegrid_reorder\MoveIntent;
use Drupal\treegrid_reorder\TreeMoveHandlerInterface;
use Drupal\treegrid_reorder\TreeReorderManager;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the universal validation and commit replay in TreeReorderManager.
 */
#[Group('treegrid_reorder')]
final class TreeReorderManagerTest extends UnitTestCase {

  /**
   * The manager under test.
   */
  private TreeReorderManager $manager;

  /**
   * A recording fake of the domain seam.
   */
  private RecordingTreeMoveHandler $handler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->manager = new TreeReorderManager();
    $this->manager->setStringTranslation($this->getStringTranslationStub());
    $this->handler = new RecordingTreeMoveHandler();
  }

  /**
   * Dropping an item onto itself is rejected before the handler is consulted.
   */
  public function testSelfTargetRejected(): void {
    $result = $this->manager->validateMove('t', new MoveIntent('a', 'a', NULL), $this->handler);

    $this->assertFalse($result['ok']);
    $this->assertSame('self_target', $result['code']);
  }

  /**
   * Dropping an item under its own descendant is rejected as a cycle.
   */
  public function testCycleRejected(): void {
    // 'a' is among the new parent 'b's ancestors → moving 'a' under 'b' cycles.
    $this->handler->ancestors = ['a', 'b'];

    $result = $this->manager->validateMove('t', new MoveIntent('a', 'b', NULL), $this->handler);

    $this->assertFalse($result['ok']);
    $this->assertSame('cycle', $result['code']);
  }

  /**
   * When the structural checks pass, the handler's outcome is returned as-is.
   */
  public function testDelegatesToHandler(): void {
    $this->handler->ancestors = ['b'];
    $this->handler->constraintResult = [
      'ok' => FALSE,
      'code' => 'too_deep',
      'message' => new FormattableMarkup('Nope.', []),
    ];

    $result = $this->manager->validateMove('t', new MoveIntent('a', 'b', NULL), $this->handler);

    $this->assertFalse($result['ok']);
    $this->assertSame('too_deep', $result['code']);
  }

  /**
   * The cycle check is skipped for a move to the root (empty parent).
   */
  public function testRootMoveSkipsCycleCheck(): void {
    $this->handler->ancestors = ['a'];

    $result = $this->manager->validateMove('t', new MoveIntent('a', '', NULL), $this->handler);

    $this->assertTrue($result['ok']);
  }

  /**
   * A working copy with no move ops commits as a no-op.
   */
  public function testCommitEmptyIsOk(): void {
    $result = $this->manager->commitWorkingCopy('t', [['type' => 'toggle']], $this->handler);

    $this->assertTrue($result['ok']);
    $this->assertSame([], $this->handler->applied);
  }

  /**
   * Committing applies every move in order.
   */
  public function testCommitAppliesAllMoves(): void {
    $ops = [
      ['type' => 'move', 'id' => 'a', 'new_parent' => '', 'before_id' => NULL],
      ['type' => 'move', 'id' => 'b', 'new_parent' => '', 'before_id' => 'a'],
    ];

    $result = $this->manager->commitWorkingCopy('t', $ops, $this->handler);

    $this->assertTrue($result['ok']);
    $this->assertSame(['a', 'b'], $this->handler->applied);
  }

  /**
   * An individually invalid op aborts the commit before anything is applied.
   */
  public function testCommitAbortsOnInvalidOp(): void {
    $this->handler->constraintResult = [
      'ok' => FALSE,
      'code' => 'not_moveable',
      'message' => new FormattableMarkup('No.', []),
    ];
    $ops = [
      ['type' => 'move', 'id' => 'a', 'new_parent' => '', 'before_id' => NULL],
    ];

    $result = $this->manager->commitWorkingCopy('t', $ops, $this->handler);

    $this->assertFalse($result['ok']);
    $this->assertSame(0, $result['failed_op']);
    $this->assertSame([], $result['applied_ops']);
    $this->assertSame([], $this->handler->applied);
  }

  /**
   * A move made invalid by an earlier applied move reports the applied index.
   *
   * The handler's second constraint check depends on what the first move
   * already applied, so it fails only once the apply loop reaches it.
   */
  public function testPartialFailureReportsAppliedOps(): void {
    $handler = new class() implements TreeMoveHandlerInterface {

      /**
       * Ids of moves applied, in order.
       *
       * @var string[]
       */
      public array $applied = [];

      /**
       * {@inheritdoc}
       */
      public function validateConstraints(string $tree_id, MoveIntent $intent): array {
        if ($intent->id === 'b' && in_array('a', $this->applied, TRUE)) {
          return ['ok' => FALSE, 'code' => 'cycle', 'message' => new FormattableMarkup('No longer valid.', [])];
        }
        return ['ok' => TRUE];
      }

      /**
       * {@inheritdoc}
       */
      public function getAncestorIds(string $tree_id, string $parent_id): array {
        return [];
      }

      /**
       * {@inheritdoc}
       */
      public function applyMove(string $tree_id, MoveIntent $intent): void {
        $this->applied[] = $intent->id;
      }

    };
    $ops = [
      ['type' => 'move', 'id' => 'a', 'new_parent' => '', 'before_id' => NULL],
      ['type' => 'move', 'id' => 'b', 'new_parent' => '', 'before_id' => 'a'],
    ];

    $result = $this->manager->commitWorkingCopy('t', $ops, $handler);

    $this->assertFalse($result['ok']);
    $this->assertSame('cycle', $result['code']);
    $this->assertSame(1, $result['failed_op']);
    $this->assertSame([0], $result['applied_ops']);
    $this->assertSame(['a'], $handler->applied);
  }

  /**
   * The batch is not validated up front, so earlier valid moves are written.
   */
  public function testInvalidLaterOpDoesNotBlockEarlierMoves(): void {
    $handler = new class() implements TreeMoveHandlerInterface {

      /**
       * Ids of moves applied, in order.
       *
       * @var string[]
       */
      public array $applied = [];

      /**
       * {@inheritdoc}
       */
      public function validateConstraints(string $tree_id, MoveIntent $intent): array {
        if ($intent->id === 'b') {
          return ['ok' => FALSE, 'code' => 'not_moveable', 'message' => new FormattableMarkup('No.', [])];
        }
        return ['ok' => TRUE];
      }

      /**
       * {@inheritdoc}
       */
      public function getAncestorIds(string $tree_id, string $parent_id): array {
        return [];
      }

      /**
       * {@inheritdoc}
       */
      public function applyMove(string $tree_id, MoveIntent $intent): void {
        $this->applied[] = $intent->id;
      }

    };
    $ops = [
      ['type' => 'move', 'id' => 'a', 'new_parent' => '', 'before_id' => NULL],
      ['type' => 'move', 'id' => 'b', 'new_parent' => '', 'before_id' => 'a'],
    ];

    $result = $this->manager->commitWorkingCopy('t', $ops, $handler);

    $this->assertFalse($result['ok']);
    $this->assertSame('not_moveable', $result['code']);
    $this->assertSame(1, $result['failed_op']);
    $this->assertSame([0], $result['applied_ops']);
    $this->assertSame(['a'], $handler->applied);
  }

}
