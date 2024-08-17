<?php

declare(strict_types=1);

namespace Drupal\Component\Utility;

/**
 * Provides helpers to insert values into arrays.
 *
 * @ingroup utility
 */
class InsertArray {

  /**
   * Inserts values into an associative array before a given key.
   *
   * Values from $insert_array are inserted into $array before $key.
   *
   * Arrays with numeric keys are supported, but indexed arrays are not, as
   * duplicate keys are not supported.
   *
   * @param array $array
   *   The array to insert into, passed by reference and altered in place.
   * @param mixed $key
   *   The key of $array to insert before.
   * @param array $insert_array
   *   An array whose keys and values should be inserted.
   *
   * @throws \InvalidArgumentException
   *   Throws an exception in the following cases:
   *    - The $array does not have the key $key.
   *    - The $array and the $insert_array have keys in common.
   */
  public static function insertBefore(array &$array, mixed $key, array $insert_array): void {
    static::insert($array, $key, $insert_array, TRUE);
  }

  /**
   * Inserts values into an associative array after a given key.
   *
   * Values from $insert_array are inserted into $array after $key.
   *
   * Arrays with numeric keys are supported, but indexed arrays are not, as
   * duplicate keys are not supported.
   *
   * @param array $array
   *   The array to insert into, passed by reference and altered in place.
   * @param mixed $key
   *   The key of $array to insert after.
   * @param array $insert_array
   *   An array whose keys and values should be inserted.
   *
   * @throws \InvalidArgumentException
   *   Throws an exception in the following cases:
   *    - The $array does not have the key $key.
   *    - The $array and the $insert_array have keys in common.
   */
  public static function insertAfter(array &$array, mixed $key, array $insert_array): void {
    static::insert($array, $key, $insert_array, FALSE);
  }

  /**
   * Inserts values into an array before or after a given key.
   *
   * Helper for insertBefore() and insertAfter().
   *
   * Values from $insert_array are inserted into $array either before or after
   * $key.
   *
   * @param array $array
   *   The array to insert into, passed by reference and altered in place.
   * @param mixed $key
   *   The key of $array to insert before or after.
   * @param array $insert_array
   *   An array whose values should be inserted.
   * @param bool $before
   *   If TRUE, insert before the given key; if FALSE, insert after it.
   *
   * @throws \InvalidArgumentException
   *   Throws an exception in the following cases:
   *    - The $array does not have the key $key.
   *    - The $array and the $insert_array have keys in common.
   */
  protected static function insert(array &$array, mixed $key, array $insert_array, bool $before): void {
    if (!isset($array[$key])) {
      throw new \InvalidArgumentException("The array does not have the key $key.");
    }
    if ($common = array_intersect_key($array, $insert_array)) {
      throw new \InvalidArgumentException("The target array and the insert array have common keys: " . implode(', ', array_keys($common)));
    }

    if ($before) {
      $offset = 0;
    }
    else {
      $offset = 1;
    }

    $position = array_search($key, array_keys($array));
    $position += $offset;

    $array_before = array_slice($array, 0, $position, preserve_keys: TRUE);
    $array_after = array_slice($array, $position, preserve_keys: TRUE);

    $array = $array_before + $insert_array + $array_after;
  }

}
