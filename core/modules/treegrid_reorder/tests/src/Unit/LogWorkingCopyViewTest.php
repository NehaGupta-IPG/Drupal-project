<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\treegrid_reorder\LogWorkingCopyView;
use Drupal\treegrid_reorder\MoveLogReplay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests LogWorkingCopyView.
 */
#[CoversClass(LogWorkingCopyView::class)]
#[Group('treegrid_reorder')]
final class LogWorkingCopyViewTest extends UnitTestCase {

  /**
   * Builds a view over moves.
   *
   * @param array<int, array<string, mixed>> $ops
   *   The ops.
   */
  private static function view(array $ops): LogWorkingCopyView {
    return new LogWorkingCopyView($ops, new MoveLogReplay());
  }

  /**
   * Builds a move op.
   *
   * @return array<string, mixed>
   *   The op.
   */
  private static function move(string $id, string $new_parent, ?string $before_id = NULL): array {
    return ['type' => 'move', 'id' => $id, 'new_parent' => $new_parent, 'before_id' => $before_id];
  }

  /**
   * Tests a reorder within a level.
   */
  public function testReorderWithinLevel(): void {
    $view = self::view([self::move('c', '', 'a')]);
    $this->assertSame(['c', 'a', 'b'], $view->levelIds('', ['a', 'b', 'c'], ['c']));
  }

  /**
   * Tests a link moved in and one moved out.
   */
  public function testMovedInAndOut(): void {
    $view = self::view([self::move('x', '', 'b'), self::move('a', 'p')]);
    $this->assertSame(['x', 'b'], $view->levelIds('', ['a', 'b'], ['x', 'a']));
    $this->assertSame(['a'], $view->levelIds('p', [], ['x', 'a']));
  }

  /**
   * Tests a level the ops don't touch.
   */
  public function testUntouchedLevel(): void {
    $view = self::view([self::move('x', 'p', 'y')]);
    $this->assertSame(['a', 'b'], $view->levelIds('q', ['a', 'b'], ['x']));
  }

  /**
   * Tests a deleted moved-in link with a later move anchored on it.
   */
  public function testDeletedMovedInLink(): void {
    $view = self::view([self::move('x', '', 'b'), self::move('y', '', 'x')]);
    $this->assertSame(['a', 'b', 'y'], $view->levelIds('', ['a', 'b'], ['y']));
  }

  /**
   * Tests pendingParent().
   */
  public function testPendingParent(): void {
    $view = self::view([self::move('a', 'p'), self::move('b', ''), self::move('c', 'p'), self::move('c', 'q')]);
    $this->assertNull($view->pendingParent('z'));
    $this->assertSame('p', $view->pendingParent('a'));
    $this->assertSame('', $view->pendingParent('b'));
    $this->assertSame('q', $view->pendingParent('c'));
  }

  /**
   * Tests that pendingIds() lists each moved link once.
   */
  public function testPendingIds(): void {
    $view = self::view([self::move('a', 'p'), self::move('b', ''), self::move('a', 'q'), self::move('a', '')]);
    $this->assertSame(['a', 'b'], $view->pendingIds());
    $this->assertSame([], self::view([])->pendingIds());
  }

}
