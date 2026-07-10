<?php

declare(strict_types=1);

namespace Drupal\TestTools;

use Drupal\Component\Utility\IntegerLike;
use PHPUnit\Framework\Assert;
use PHPUnit\Util\Exporter;

/**
 * Contains assertion methods that treat integer-like values like integer.
 *
 * @see \Drupal\Component\Utility\IntegerLike
 */
class IntegerLikeAssert {

  /**
   * Asserts two values to be the same, with string/int equivalence.
   *
   * @param mixed $expected
   *   Expected value.
   * @param mixed $actual
   *   Actual value.
   * @param string $message
   *   (optional) Message to show when the assertion fails.
   */
  public static function same(mixed $expected, mixed $actual, string $message = ''): void {
    $same = IntegerLike::same($expected, $actual);
    if ($same) {
      // Easiest way to add to assertion count.
      Assert::assertTrue(TRUE);
      return;
    }
    Assert::fail(sprintf(
      "%sExpected %s, with int/string cast allowed.\nFound %s.",
      ($message !== '') ? $message . "\n" : '',
      Exporter::export($expected),
      Exporter::export($actual),
    ));
  }

  /**
   * Asserts two values to be the same, with string/int recursive equivalence.
   *
   * @param mixed $expected
   *   Expected value.
   * @param mixed $actual
   *   Actual value.
   * @param list<string> $parents
   *   Parent keys, to show in a failure message.
   */
  public static function sameRecursive(mixed $expected, mixed $actual, array $parents = []): void {
    $fail = fn (): never => Assert::fail(sprintf(
      "Mismatch at offset %s.\nExpected: %s\nActual: %s",
      '[' . implode('][', $parents),
      Exporter::export($expected),
      Exporter::export($actual),
    ));
    if (is_array($expected)) {
      if (!is_array($actual)) {
        $fail();
      }
      if (array_keys($expected) !== array_keys($actual)) {
        $fail();
      }
      foreach ($expected as $key => $value) {
        static::sameRecursive($value, $actual[$key], [...$parents, $key]);
      }
    }
    if (!IntegerLike::same($expected, $actual)) {
      $fail();
    }
  }

  /**
   * Asserts that a haystack contains a needle, with string/int equivalence.
   *
   * @param mixed $needle
   *   The value expected to be in the haystack.
   * @param mixed $haystack
   *   The haystack.
   */
  public static function inArray(mixed $needle, mixed $haystack): void {
    $in_array = IntegerLike::inArray($needle, $haystack);
    if ($in_array) {
      // Easiest way to add to assertion count.
      Assert::assertTrue(TRUE);
      return;
    }
    Assert::fail(sprintf(
      "Expected a value to be one of %s, with int/string cast allowed.\nFound %s.",
      Exporter::export($haystack),
      Exporter::export($needle),
    ));
  }

}
