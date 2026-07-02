<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\menu_tree_ui\Service\MenuTreeLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests MenuTreeLoader::spliceBefore().
 */
#[CoversClass(MenuTreeLoader::class)]
#[Group('menu_tree_ui')]
final class MenuTreeSpliceBeforeTest extends UnitTestCase {

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
    $this->assertSame($expected, MenuTreeLoader::spliceBefore($order, $moved, $before_id));
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

}
