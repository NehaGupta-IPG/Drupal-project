<?php

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
  public static function dataInsertBefore() {
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
    ];
  }

  /**
   * Tests \Drupal\Component\Utility\InsertArray::insertBefore().
   */
  #[DataProvider('dataInsertBefore')]
   public function testInsertBefore(array $array, string $key, array $insert_array, array $expected_array) {
     InsertArray::insertBefore($array, $key, $insert_array);
     $this->assertSame($expected_array, $array);
  }

  /**
   * Data provider for testInsertAfter().
   */
  public static function dataInsertAfter() {
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
    ];
  }

  /**
   * Tests \Drupal\Component\Utility\InsertArray::insertAfter().
   */
  #[DataProvider('dataInsertAfter')]
  public function testInsertAfter(array $array, string $key, array $insert_array, array $expected_array) {
    InsertArray::insertAfter($array, $key, $insert_array);
    $this->assertSame($expected_array, $array);
  }

}
