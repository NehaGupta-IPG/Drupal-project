<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Utility\InsertArray;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\Component\Utility\InsertArray
 * @group Utility
 */
class InsertArrayTest extends TestCase {

  /**
   * Data provider for testInsertBefore().
   */
  public static function dataInsertBefore(): array {
    return [
      'in_between' => [
        [
          'first' => 'first item',
          'third' => 'third item',
        ],
        'third',
        [
          'second' => 'second item',
        ],
        [
          'first' => 'first item',
          'second' => 'second item',
          'third' => 'third item',
        ],
      ],
      'first' => [
        [
          'first' => 'first item',
          'second' => 'second item',
        ],
        'first',
        [
          'zero' => 'zero item',
        ],
        [
          'zero' => 'zero item',
          'first' => 'first item',
          'second' => 'second item',
        ],
      ],
      'in_between_numeric' => [
        [
          3 => 3,
          2 => 2,
          1 => 1,
        ],
        1,
        [
          4 => 4,
        ],
        [
          3 => 3,
          2 => 2,
          4 => 4,
          1 => 1,
        ],
      ],
      'first_numeric' => [
        [
          3 => 3,
          2 => 2,
          1 => 1,
        ],
        3,
        [
          4 => 4,
        ],
        [
          4 => 4,
          3 => 3,
          2 => 2,
          1 => 1,
        ],
      ],
    ];
  }

  /**
   * Tests \Drupal\Component\Utility\InsertArray::insertBefore().
   */
  #[DataProvider('dataInsertBefore')]
  public function testInsertBefore(array $array, mixed $key, array $insert_array, array $expected_array): void {
    InsertArray::insertBefore($array, $key, $insert_array);
    $this->assertSame($expected_array, $array);
  }

  /**
   * Data provider for testInsertAfter().
   */
  public static function dataInsertAfter(): array {
    return [
      'in_between' => [
        [
          'first' => 'first item',
          'third' => 'third item',
        ],
        'first',
        [
          'second' => 'second item',
        ],
        [
          'first' => 'first item',
          'second' => 'second item',
          'third' => 'third item',
        ],
      ],
      'last' => [
        [
          'first' => 'first item',
          'second' => 'second item',
        ],
        'second',
        [
          'last' => 'last item',
        ],
        [
          'first' => 'first item',
          'second' => 'second item',
          'last' => 'last item',
        ],
      ],
      'in_between_numeric' => [
        [
          3 => 3,
          2 => 2,
          1 => 1,
        ],
        2,
        [
          4 => 4,
        ],
        [
          3 => 3,
          2 => 2,
          4 => 4,
          1 => 1,
        ],
      ],
      'last_numeric' => [
        [
          3 => 3,
          2 => 2,
          1 => 1,
        ],
        1,
        [
          4 => 4,
        ],
        [
          3 => 3,
          2 => 2,
          1 => 1,
          4 => 4,
        ],
      ],
    ];
  }

  /**
   * Tests \Drupal\Component\Utility\InsertArray::insertAfter().
   */
  #[DataProvider('dataInsertAfter')]
  public function testInsertAfter(array $array, mixed $key, array $insert_array, array $expected_array): void {
    InsertArray::insertAfter($array, $key, $insert_array);
    $this->assertSame($expected_array, $array);
  }

  /**
   * Data provider for testExceptions().
   */
  public static function dataExceptions(): array {
    return [
      'nonexistent_key' => [
        [
          'first' => 'first item',
          'third' => 'third item',
        ],
        'nonexistent',
        [
          'first' => 'first item',
        ],
      ],
      'repeated_key' => [
        [
          'first' => 'first item',
          'third' => 'third item',
        ],
        'first',
        [
          'first' => 'duplicate item',
        ],
      ],
    ];
  }

  /**
   * Tests exceptions thrown by InsertArray.
   */
  #[DataProvider('dataExceptions')]
  public function testExceptions(array $array, mixed $key, array $insert_array): void {
    $this->expectException(\InvalidArgumentException::class);
    // It doesn't matter whether we use insertAfter() or insertBefore() as both
    // use the same helper method.
    InsertArray::insertAfter($array, $key, $insert_array);
  }

}
