<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Utility;

use Drupal\Core\Utility\Error;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Utility\Error.
 */
#[CoversClass(Error::class)]
#[Group('Utility')]
class ErrorTest extends UnitTestCase {

  /**
   * Tests the getLastCaller() method.
   *
   * @param array $backtrace
   *   The test backtrace array.
   * @param array $expected
   *   The expected return array.
   */
  #[DataProvider('providerTestGetLastCaller')]
  public function testGetLastCaller(array $backtrace, array $expected): void {
    $this->assertSame($expected, Error::getLastCaller($backtrace));
  }

  /**
   * Data provider for testGetLastCaller.
   *
   * @return \Iterator<string, array{list<array>, array}>
   *   An iterator with named datasets.
   *   (Using an iterator instead of an array helps to avoid duplicate dataset
   *   names.)
   */
  public static function providerTestGetLastCaller(): \Iterator {
    // Test with just one item. This should default to the function being
    // main().
    yield 'single item' => [
      [
        self::createBacktraceItem('foo', line: 51, file: 'main.php'),
      ],
      self::createBacktraceItem('main()', line: 51, file: 'main.php'),
    ];

    // Test with more procedural items.
    // The function name from the first item is always ignored.
    yield 'bar, foo, other' => [
      [
        self::createBacktraceItem('bar', line: 55, file: 'foo.php'),
        self::createBacktraceItem('foo', line: 66, file: 'other.php'),
        self::createBacktraceItem('other', line: 77, file: 'main.php'),
      ],
      self::createBacktraceItem('foo()', line: 55, file: 'foo.php'),
    ];

    // Test with a class method as second item.
    yield 'TestClass' => [
      [
        self::createBacktraceItem('foo', file: 'TestClass.php'),
        self::createBacktraceItem('testMethod', 'TestClass', file: 'other.php'),
        self::createBacktraceItem('other', file: 'main.php'),
      ],
      self::createBacktraceItem('TestClass->testMethod()', file: 'TestClass.php'),
    ];

    // Test with NULL as the first item's function name.
    // This replicates the behavior from Error::decodeException(), which
    // prepends an artificial item without function name.
    yield 'NULL, bar, foo' => [
      [
        self::createBacktraceItem(NULL, line: 77, file: 'bar.php'),
        self::createBacktraceItem('bar', file: 'foo.php'),
        self::createBacktraceItem('foo', file: 'index.php'),
      ],
      self::createBacktraceItem('bar()', line: 77, file: 'bar.php'),
    ];

    // Prepend ignored functions to backtrace. They should get removed.
    foreach (['debug', '_drupal_error_handler', '_drupal_exception_handler'] as $function) {
      yield "NULL, $function, bar, foo" => [
        [
          self::createBacktraceItem(NULL),
          self::createBacktraceItem($function, file: 'bar.php'),
          self::createBacktraceItem('bar', file: 'foo.php'),
          self::createBacktraceItem('foo', file: 'index.php'),
        ],
        self::createBacktraceItem('bar()', file: 'bar.php'),
      ];
    }

    // Test removal of multiple ignored functions.
    yield 'NULL, debug, debug' => [
      [
        self::createBacktraceItem(NULL),
        self::createBacktraceItem('debug'),
        self::createBacktraceItem('_drupal_exception_handler'),
        self::createBacktraceItem('foo'),
      ],
      self::createBacktraceItem('foo()'),
    ];
  }

  /**
   * Tests the formatBacktrace() method.
   *
   * @param array $backtrace
   *   The test backtrace array.
   * @param string $expected
   *   The expected backtrace as a string.
   */
  #[DataProvider('providerTestFormatBacktrace')]
  public function testFormatBacktrace(array $backtrace, string $expected): void {
    $this->assertSame($expected, Error::formatBacktrace($backtrace));
  }

  /**
   * Data provider for testFormatBacktrace.
   *
   * @return array
   *   An array of test cases, each containing a backtrace array and its expected formatted string.
   */
  public static function providerTestFormatBacktrace(): array {
    $data = [];

    // Test with no function, main should be in the backtrace.
    $data[] = [[self::createBacktraceItem(NULL, NULL)], "main() (Line: 10)\n"];

    $base = [self::createBacktraceItem()];
    $data[] = [$base, "test_function() (Line: 10)\n"];

    // Add a second item.
    $second_item = $base;
    $second_item[] = self::createBacktraceItem('test_function_2');

    $data[] = [$second_item, "test_function() (Line: 10)\ntest_function_2() (Line: 10)\n"];

    // Add a second item, with a class.
    $second_item_class = $base;
    $second_item_class[] = self::createBacktraceItem('test_function_2', 'TestClass');

    $data[] = [$second_item_class, "test_function() (Line: 10)\nTestClass->test_function_2() (Line: 10)\n"];

    // Add a second item, with a class.
    $second_item_args = $base;
    $second_item_args[] = self::createBacktraceItem('test_function_2', NULL, ['string', 10, new \stdClass()]);

    $data[] = [$second_item_args, "test_function() (Line: 10)\ntest_function_2('string', 10, Object) (Line: 10)\n"];

    return $data;
  }

  /**
   * Creates a mock backtrace item.
   *
   * @param string|null $function
   *   (optional) The function name to use in the backtrace item.
   * @param string|null $class
   *   (optional) The class to use in the backtrace item.
   * @param array $args
   *   (optional) An array of function arguments to add to the backtrace item.
   * @param int $line
   *   (optional) The line where the function was called.
   * @param string|null $file
   *   (optional) The file where the function was called.
   *
   * @return array
   *   A backtrace array item.
   */
  protected static function createBacktraceItem(?string $function = 'test_function', ?string $class = NULL, array $args = [], int $line = 10, ?string $file = 'test_file'): array {
    $backtrace = [
      'file' => $file,
      'line' => $line,
      'function' => $function,
      'args' => [],
    ];

    if (isset($class)) {
      $backtrace['class'] = $class;
      $backtrace['type'] = '->';
    }

    if (!empty($args)) {
      $backtrace['args'] = $args;
    }

    return $backtrace;
  }

}
