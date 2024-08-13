<?php

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Utility\InsertArray;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\Component\Utility\InsertArray
 * @group Utility
 */
class InsertArrayTest extends TestCase {

  /**
   * Tests \Drupal\Component\Utility\InsertArray::insertBefore().
   */
  public function testInsertBefore() {
    // Test that we can insert an item in between other items in an associative
    // array.
    $array = [
      'first' => 'first item',
      'third' => 'third item',
    ];

    $second = [
      'second' => 'second item',
    ];

    InsertArray::insertBefore($array, 'third', $second);

    $expected_array = [
      'first' => 'first item',
      'second' => 'second item',
      'third' => 'third item',
    ];
    $this->assertSame($expected_array, $array, "The item was inserted before another item in the associative array.");

    // Test that we can properly insert an item to the beginning of an
    // associative array.
    $zero = [
      'zero' => 'zero item',
    ];

    InsertArray::insertBefore($array, 'first', $zero);

    $expected_array = [
      'zero' => 'zero item',
      'first' => 'first item',
      'second' => 'second item',
      'third' => 'third item',
    ];

    $this->assertSame($expected_array, $array, "The item was inserted at the beginning of the associative array.");
  }

  /**
   * Tests \Drupal\Component\Utility\InsertArray::insertAfter().
   */
  public function testInsertAfter() {
    // Test that we can insert an item in between other items in an associative
    // array.
    $array = [
      'first' => 'first item',
      'third' => 'third item',
    ];

    $second = [
      'second' => 'second item',
    ];

    InsertArray::insertAfter($array, 'first', $second);

    $expected_array = [
      'first' => 'first item',
      'second' => 'second item',
      'third' => 'third item',
    ];

    $this->assertSame($expected_array, $array, "The item was inserted after another item in the associative array.");

    // Test that we can properly insert an item to the end of an associative
    // array.
    $last = [
      'last' => 'last item',
    ];

    InsertArray::insertAfter($array, 'third', $last);

    $expected_array = [
      'first' => 'first item',
      'second' => 'second item',
      'third' => 'third item',
      'last' => 'last item',
    ];

    $this->assertSame($expected_array, $array, "The item was inserted at the end of the associative array.");
  }

}
