<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\treegrid_reorder\MoveLogReplay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests MoveLogReplay.
 */
#[CoversClass(MoveLogReplay::class)]
#[Group('treegrid_reorder')]
final class MoveLogReplayTest extends UnitTestCase {

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
   * Tests spliceBefore() against a table of orderings.
   *
   * @param string[] $order
   *   The current ordered ids.
   * @param string $moved
   *   The id to (re)place.
   * @param string|null $before_id
   *   The anchor id, or NULL to append.
   * @param string[] $expected
   *   The expected resulting order.
   */
  #[DataProvider('providerSpliceBefore')]
  public function testSpliceBefore(array $order, string $moved, ?string $before_id, array $expected): void {
    $this->assertSame($expected, (new MoveLogReplay())->spliceBefore($order, $moved, $before_id));
  }

  /**
   * Data provider for testSpliceBefore().
   *
   * @return array<string, array{0: string[], 1: string, 2: string|null, 3: string[]}>
   *   Test cases keyed by description.
   */
  public static function providerSpliceBefore(): array {
    return [
      'insert before a middle id' => [
        ['a', 'b', 'c'],
        'd',
        'b',
        ['a', 'd', 'b', 'c'],
      ],
      'insert before the first id' => [
        ['a', 'b', 'c'],
        'd',
        'a',
        ['d', 'a', 'b', 'c'],
      ],
      'before_id NULL appends' => [
        ['a', 'b', 'c'],
        'd',
        NULL,
        ['a', 'b', 'c', 'd'],
      ],
      'before_id absent from the list appends' => [
        ['a', 'b', 'c'],
        'd',
        'nonexistent',
        ['a', 'b', 'c', 'd'],
      ],
      'moved not currently in list is inserted at the right spot' => [
        ['a', 'c'],
        'b',
        'c',
        ['a', 'b', 'c'],
      ],
      'moved already present is repositioned, not duplicated' => [
        ['a', 'b', 'c'],
        'a',
        'c',
        ['b', 'a', 'c'],
      ],
      'moved already immediately before its own anchor is a no-op order' => [
        ['a', 'b', 'c'],
        'b',
        'c',
        ['a', 'b', 'c'],
      ],
      'single-element list, append' => [
        ['a'],
        'b',
        NULL,
        ['a', 'b'],
      ],
      'single-element list, moved is the only element and re-appends itself' => [
        ['a'],
        'a',
        NULL,
        ['a'],
      ],
      'empty list appends' => [
        [],
        'a',
        NULL,
        ['a'],
      ],
      'empty list with a before_id still appends, since there is nothing to match' => [
        [],
        'a',
        'b',
        ['a'],
      ],
    ];
  }

  /**
   * Tests that the last move of an id wins.
   */
  public function testPendingParentsLastMoveWins(): void {
    $ops = [self::move('a', 'p'), self::move('b', 'q'), self::move('a', 'r')];
    $this->assertSame(['a' => 'r', 'b' => 'q'], (new MoveLogReplay())->pendingParents($ops));
  }

  /**
   * Tests that ops other than moves are ignored.
   */
  public function testPendingParentsIgnoresOtherOps(): void {
    $ops = [['type' => 'other', 'id' => 'a', 'new_parent' => 'p'], self::move('b', 'q')];
    $this->assertSame(['b' => 'q'], (new MoveLogReplay())->pendingParents($ops));
  }

  /**
   * Tests that a move to the top maps to an empty string.
   */
  public function testPendingParentsTop(): void {
    $this->assertSame(['a' => ''], (new MoveLogReplay())->pendingParents([self::move('a', '')]));
  }

  /**
   * Tests that ids without moves have no key.
   */
  public function testPendingParentsNoKeyWithoutMoves(): void {
    $map = (new MoveLogReplay())->pendingParents([self::move('a', 'p')]);
    $this->assertArrayNotHasKey('b', $map);
    $this->assertSame([], (new MoveLogReplay())->pendingParents([]));
  }

  /**
   * Tests replayLevel() against a table of logs.
   *
   * @param string $parent_id
   *   The level's parent id.
   * @param string[] $live_ids
   *   The live ids.
   * @param array<int, array<string, mixed>> $ops
   *   The ops.
   * @param string[] $existing_ids
   *   The moved ids that still exist.
   * @param string[] $expected
   *   The expected ids.
   */
  #[DataProvider('providerReplayLevel')]
  public function testReplayLevel(string $parent_id, array $live_ids, array $ops, array $existing_ids, array $expected): void {
    $this->assertSame($expected, (new MoveLogReplay())->replayLevel($parent_id, $live_ids, $ops, $existing_ids));
  }

  /**
   * Data provider for testReplayLevel().
   *
   * @return array<string, array{0: string, 1: string[], 2: array<int, array<string, mixed>>, 3: string[], 4: string[]}>
   *   Test cases keyed by description.
   */
  public static function providerReplayLevel(): array {
    return [
      'reorder within the level' => [
        '', ['a', 'b', 'c'], [self::move('c', '', 'a')], ['c'], ['c', 'a', 'b'],
      ],
      'moved in before an anchor' => [
        '', ['a', 'b'], [self::move('x', '', 'b')], ['x'], ['a', 'x', 'b'],
      ],
      'moved in at the end' => [
        '', ['a', 'b'], [self::move('x', '')], ['x'], ['a', 'b', 'x'],
      ],
      'moved out' => [
        '', ['a', 'b', 'c'], [self::move('b', 'p')], ['b'], ['a', 'c'],
      ],
      'moved in then out again' => [
        '', ['a', 'b'], [self::move('x', '', 'a'), self::move('x', 'p')], ['x'], ['a', 'b'],
      ],
      'anchor that has itself left the level' => [
        '', ['a', 'b', 'c'], [self::move('b', 'p'), self::move('c', '', 'b')], ['b', 'c'], ['a', 'c'],
      ],
      'chain of placements' => [
        '', ['x', 'y'], [self::move('y', '', 'x'), self::move('z', '', 'y')], ['y', 'z'], ['z', 'y', 'x'],
      ],
      'level the ops never touch comes back unchanged' => [
        'q', ['a', 'b'], [self::move('c', 'p', 'd')], ['c'], ['a', 'b'],
      ],
      'moved-in id that no longer exists is skipped and a later move lands at the end' => [
        '', ['a', 'b'], [self::move('x', '', 'b'), self::move('y', '', 'x')], ['y'], ['a', 'b', 'y'],
      ],
    ];
  }

}
